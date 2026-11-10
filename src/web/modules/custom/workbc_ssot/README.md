# WorkBC SSoT module (`workbc_ssot`)

Drupal 11 module that lets authorised editors upload Excel spreadsheets which are
parsed, validated, converted to one or more datasets, written to the **SSoT** (Single
Source of Truth) PostgREST database, and — asynchronously — committed to the
[`bcgov/workbc-ssot`](https://github.com/bcgov/workbc-ssot) GitHub repository where a
workflow regenerates the SSoT snapshot.

## Contents

- [Overview](#overview)
- [End-to-end flow](#end-to-end-flow)
- [Upload forms](#upload-forms)
  - [Labour Market Monthly Update (LMMU)](#labour-market-monthly-update-lmmu)
  - [Industry Profile Yearly Update](#industry-profile-yearly-update)
  - [Labour Force Survey Yearly Update](#labour-force-survey-yearly-update)
- [`SsotUploadFormBase`: the TOC-driven engine](#ssotuploadformbase-the-toc-driven-engine)
  - [Form-level declarations](#form-level-declarations)
  - [TOC entry schema](#toc-entry-schema)
  - [Shapes: `record` vs `table`](#shapes-record-vs-table)
  - [Period handling](#period-handling)
  - [Write modes and `sources`](#write-modes-and-sources)
- [Validation rules (`SsotUploadValidation`)](#validation-rules-ssotuploadvalidation)
- [Background job (`SsotUploader` queue worker)](#background-job-ssotuploader-queue-worker)
- [Configuration](#configuration)
- [Database: `workbc_ssot_log`](#database-workbc_ssot_log)
- [Adding a new upload form](#adding-a-new-upload-form)
- [Tests](#tests)
- [Known issues / TODO](#known-issues--todo)

## Overview

| Path | Purpose |
|------|---------|
| [`src/Form/SsotUploadFormBase.php`](./src/Form/SsotUploadFormBase.php) | Abstract base: two-step confirm flow, workbook reading, validation, SSoT writes, `sources`, log, queue. |
| [`src/Form/SsotUploadValidation.php`](./src/Form/SsotUploadValidation.php) | Trait: SSoT HTTP helper, name→key lookups, and the declarative cell validation engine. |
| [`src/Form/SsotUploadLmmuForm.php`](./src/Form/SsotUploadLmmuForm.php) | Monthly LMMU spreadsheet → `monthly_labour_market_updates`. |
| [`src/Form/SsotUploadIndustryProfileForm.php`](./src/Form/SsotUploadIndustryProfileForm.php) | Yearly Industry Profile spreadsheet → `industry_outlook`. |
| [`src/Form/SsotUploadLabourForceSurveyForm.php`](./src/Form/SsotUploadLabourForceSurveyForm.php) | Yearly LFS data sheet (2 tabs) → 4 `labour_force_survey_*` datasets. |
| [`src/Plugin/QueueWorker/SsotUploader.php`](./src/Plugin/QueueWorker/SsotUploader.php) | Cron queue worker that pushes the uploaded file to GitHub and dispatches a workflow. |
| [`src/Plugin/views/filter/SsotDatasetFilter.php`](./src/Plugin/views/filter/SsotDatasetFilter.php) | Views exposed filter for the upload log: dataset drop-down fed by the SSoT `sources` API, matching comma-separated `dataset_name` values. |
| [`js/validation-toggle.js`](./js/validation-toggle.js) | Hides ✅ "success" messages on the confirmation screen behind a checkbox. |
| [`workbc_ssot.install`](./workbc_ssot.install) | Defines the `workbc_ssot_log` operations-log table. |
| [`workbc_ssot.routing.yml`](./workbc_ssot.routing.yml) | Admin routes under `/admin/config/workbc/ssot/*` (permission `administer ssot settings`). |

Routes:

| Route | Path | Form |
|-------|------|------|
| `workbc_ssot.lmmu` | `/admin/config/workbc/ssot/lmmu` | `SsotUploadLmmuForm` |
| `workbc_ssot.industryprofile` | `/admin/config/workbc/ssot/industry-profile` | `SsotUploadIndustryProfileForm` |
| `workbc_ssot.lfs` | `/admin/config/workbc/ssot/labour-force-survey` | `SsotUploadLabourForceSurveyForm` |
| `workbc_ssot.refresh` | `/admin/config/workbc/ssot/refresh` | `SsotRefreshForm` (not covered here) |

## End-to-end flow

```
┌──────────────┐  1. upload .xlsx   ┌─────────────────────────────┐
│   Editor     │ ─────────────────► │ Step 1: input form          │
│ (browser)    │                    │  year[/month], file,        │
└──────────────┘                    │  timestamp, notes, flags    │
                                    └──────────────┬──────────────┘
                                                   │ validateForm()
                                                   ▼
                                    ┌─────────────────────────────┐
                                    │ PhpSpreadsheet: load file   │
                                    │ check all TOC tabs exist    │
                                    │ optional period check cell  │
                                    │ for each TOC dataset:       │
                                    │   read → rows               │
                                    │   fetch crosscheck (opt.)   │
                                    │   validateRules() per row   │
                                    └──────────────┬──────────────┘
                                   errors ◄────────┤ no errors (or warn_only)
                                                   ▼
                                    ┌─────────────────────────────┐
                                    │ Step 2: confirmation screen │
                                    │  all ✅/❗ results shown     │
                                    │  "Are you sure?"            │
                                    └──────────────┬──────────────┘
                                                   │ submitForm()
                                                   ▼
            ┌──────────────────────────────────────────────────────────────┐
            │ for each TOC dataset:                                        │
            │   a. replace (DELETE+POST) or upsert (GET→PATCH|POST)        │
            │   b. upsert `sources` row (period, date, file, sheet, range) │
            │ c. File::setPermanent()                                      │
            │ d. INSERT one `workbc_ssot_log` row (dataset_name = a,b,c)   │
            │ e. if "Update SSoT repo": queue `ssot_uploader` item         │
            │ f. Cache::invalidateTags(['rendered'])                       │
            └──────────────────────────────┬───────────────────────────────┘
                                           │ cron
                                           ▼
            ┌──────────────────────────────────────────────────────────────┐
            │ SsotUploader::processItem()                                   │
            │  1. GET  /repos/{name}/contents/{path}/{file}?ref={branch}    │
            │  2. PUT  /repos/{name}/contents/{path}/{file}  (commit)       │
            │  3. POST /repos/{name}/dispatches  (repository_dispatch)      │
            └──────────────────────────────┬───────────────────────────────┘
                                           ▼
            ┌──────────────────────────────────────────────────────────────┐
            │ workbc-ssot/.github/workflows/ssot-update.yml                 │
            │  checkout client_payload.branch → run migrator → pg_dump      │
            └──────────────────────────────────────────────────────────────┘
```

Validation happens **entirely before any write**. PostgREST has no cross-request
transactions, so if dataset *N* fails mid-way, datasets 1..*N-1* stay written; the
error message lists which ones succeeded, and a re-upload is idempotent.

## Upload forms

Every form is a thin declaration on top of `SsotUploadFormBase`. Common fields:

| Field | Type | Notes |
|-------|------|-------|
| `year` (+ `month` when granularity is `month`) | number / select | Period of the data. Optionally checked against a cell in the sheet. |
| `source_file` | `managed_file` | `.xlsx` only, stored in `private://ssot/`. |
| `timestamp` | datetime | File-system timestamp of the sheet; cannot be in the future. Stored in `sources.date` and `workbc_ssot_log.file_timestamp`. |
| `notes` | textarea (≤250) | Becomes the GitHub commit message and `workbc_ssot_log.notes`. |
| `warn_only` | checkbox | *Ignore validation errors* — skips the error gate; all rules still display as warnings on the confirmation screen. |
| `repo` | checkbox | *Update SSoT repo* — uncheck to skip the GitHub queue job. Default is per form (`$repo_default`). |

### Labour Market Monthly Update (LMMU)

- Granularity `month`; period check: `Sheet3!A3` as an Excel serial date.
- One `record`-shaped dataset `monthly_labour_market_updates` (≈90 scattered cells on
  `Sheet3`, plus `month`/`year` injected via `value` placeholders).
- Write: `upsert` matched on `year=eq.{year}&month=eq.{month}`.
- Crosscheck: previous month's row is fetched from SSoT so `crosscheck_*` rules can
  compare "previous" columns against the prior upload.
- Queue event `monthly_labour_market_update` (handled by `ssot-update.yml`).

### Industry Profile Yearly Update

- Granularity `year`; period check: `Sheet1!C2` as a plain year.
- One `table`-shaped dataset `industry_outlook`, range `Sheet1!A3:H20` (18 industries ×
  8 columns). `industry` is a lookup column resolved to the SSoT industry key.
- Write: `replace` (the table is a snapshot with no year column).
- Queue event `industry_outlook_update` (**no workflow listens for it yet**).

### Labour Force Survey Yearly Update

- Granularity `year`; **no period check** — the workbook has no reliable date cell, so
  the form's year is trusted. `repo` defaults to **off** (no workflow exists yet).
- Four `table` datasets across two tabs, ranges copied from `sources.range`:

  | Dataset | Tab | Range | Key column |
  |---|---|---|---|
  | `labour_force_survey_industry` | Industry Profiles | `A5:BC23` | `industry` (exception: `Total, All Industries`) |
  | `labour_force_survey_regional_employment` | Regional Profiles | `A6:V13` | `region` |
  | `labour_force_survey_regional_industry_region` | Regional Profiles | `A21:AK28` | `region` |
  | `labour_force_survey_regional_industry_province` | Regional Profiles | `A35:U42` | `region` |

- Field lists mirror the CSV column lists in the corresponding
  `workbc-ssot/migration/load/*.load` files. Types were inferred from column names
  (`_num`→`abs`, `_pct`/rates/shares→`pct`, `yoy_*`→`chg_*`, `earnings_*`→`num`).
- `fill_down` replicates `csv_refill.php` for the vertically merged "B.C. average"
  columns on the Industry Profiles tab.
- The "British Columbia" row of `regional_industry_province` is intentionally blank
  in the source and yields ❗ warnings, not errors.
- Write: `replace` for all four; `sources` rows are matched by endpoint.

## `SsotUploadFormBase`: the TOC-driven engine

### Form-level declarations

```php
class SsotUploadFooForm extends SsotUploadFormBase {
  protected string  $id            = 'foo';                  // form id ssot_foo_form
  protected string  $title         = 'Foo Update';           // UI messages
  protected string  $label         = '{year} Foo Survey';    // sources.label (placeholders allowed)
  protected ?string $author        = 'BC LMIO';              // sources.author (optional)
  protected string  $event_type    = 'foo_update';           // GitHub repository_dispatch type
  protected bool    $repo_default  = TRUE;                   // default of the "Update SSoT repo" box
  protected ?string $frontend_path = '/some/page';           // success message link (optional)
  protected array   $period        = [...];                  // see "Period handling"
  protected array   $toc           = [...];                  // see "TOC entry schema"

  protected function getRouteName(): string { return 'workbc_ssot.foo'; }
}
```

### TOC entry schema

```php
'<endpoint>' => [
  'sheet'      => 'Sheet3',                        // tab title, case-insensitive
  'shape'      => 'record' | 'table',
  'range'      => 'A5:BC23',                       // table only
  'write'      => 'replace'                        // DELETE all rows, then POST
               |  ['mode' => 'upsert', 'match' => 'year=eq.{year}&month=eq.{month}'],
  'sources'    => 'endpoint' | 'period',           // optional; default 'period' for upsert, 'endpoint' for replace
  'crosscheck' => 'endpoint?year=eq.{prev_year}&month=eq.{prev_month}',  // record only, optional
  'fill_down'  => ['field', ...],                  // table only, optional
  'fields'     => [
    '<field>' => ['cell' => 'B3', 'type' => 'abs', ...rules],   // record: absolute cell
    '<field>' => ['type' => 'pct', ...rules],                   // table: column derived from range + position
    '<field>' => ['value' => '{year}'],                         // literal/interpolated, consumes no column
  ],
],
```

Placeholders available everywhere a string is interpolated: `{year}`, `{month}`,
`{prev_year}`, `{prev_month}`.

### Shapes: `record` vs `table`

| | `record` | `table` |
|---|---|---|
| Source | scattered absolute cells (`B18`, `C41`, …) | rectangular `range`, read with `rangeToArray()` |
| Output | exactly 1 row | 1 row per sheet row in the range |
| `fields` order | irrelevant | **must match the column order**; the base throws a `LogicException` if the count of cell fields ≠ range width |
| Cell address in messages | `B18` | column + row, e.g. `Industry Profiles!F9` |
| Crosscheck | supported | not yet |
| `fill_down` | n/a | blank cell → previous row's value (merged cells) |

Both shapes normalise "not available" markers (`-`, `..`, `x`, `n/a`, `not available`,
empty) to `NULL`; formulas are evaluated (`getCalculatedValue()`).

### Period handling

```php
protected array $period = [
  'granularity' => 'month' | 'year',
  'check' => ['sheet' => 'Sheet3', 'cell' => 'A3', 'format' => 'excel_date' | 'year'],  // optional
];
```

One period per upload. `granularity` decides whether the month selector is shown and
how `sources.period` (`Y/MM/01 08:00`, month `01` for yearly), `workbc_ssot_log.dataset_period`
and the success message are formatted. When `check` is present the cell must agree with
the form selection, otherwise validation fails pointing at that cell.

### Write modes and `sources`

- `replace`: `DELETE /<endpoint>` then `POST` all rows. Use for snapshot tables that
  have no period column.
- `upsert`: `GET /<endpoint>?<match>`; `PATCH` if found, `POST` otherwise. Payload is a
  single object for records, an array for tables.
- `sources` upsert per dataset: `{period, date, filename, endpoint, sheet, range, label[, author]}`,
  matched by `endpoint` (+ `period` when `sources = 'period'`).

Before writing, `prepareRows()` resolves lookup columns (`industry`, `region`) to SSoT
keys using the same normalisation as the SQL functions `normalize_industry_name()` /
`normalize_region_name()` in `workbc-ssot`, and casts numeric strings to numbers.

## Validation rules (`SsotUploadValidation`)

`validateRules(array $rules, array $row, ?array $crosscheck, ?int $rowNumber, string $sheet)`
returns `[['result' => 'error'|'warning'|'success', 'message' => …], …]`. A passing
cell produces a **success** entry so the confirmation screen can list what was checked
(hidden behind the *Show successful validations* toggle).

### Per-cell `type` rules

| Type | Passes when |
|------|-------------|
| `abs` | numeric, ≥ 0, integer |
| `num` | numeric, ≥ 0, ≤ 2 decimal places (e.g. hourly earnings) |
| `pct` | numeric, 0 ≤ v ≤ 100, ≤ 1 decimal place |
| `chg_abs` | numeric, integer (sign allowed) |
| `chg_pct` | numeric, \|v\| ≤ 50, ≤ 1 decimal place |
| `industry` | resolves to an SSoT industry key; `'exceptions' => [...]` keeps listed names verbatim |
| `region` | resolves to an SSoT region key (`ssotRegions()` names + migrator aliases) |

A blank/non-numeric value for a numeric type is a **warning** ("will be shown as Not
available"). A blank or unknown lookup value is an **error** (it is the primary key).

### Inter-cell rules

| Rule | Meaning |
|------|---------|
| `'same_sign' => 'other'` | both values share the sign |
| `'equal' => 'other'` | values are equal |
| `'sum' => [['a','b'], ['c','d']]` | each group sums to this cell (±100) |
| `'crosscheck_value' => 'other'` | equals the prior period's `other` |
| `'crosscheck_change_abs' => 'other'` | equals `other_now − other_prev` |
| `'crosscheck_change_pct' => 'other'` | equals `round((now−prev)·100/now, 1)` |
| `'warn_only' => ['sum', …]` | downgrade the listed rules from error to warning |

Every rule defaults to `error`. In `validateForm()` only errors block submission (unless
*Ignore validation errors* is checked).

## Background job (`SsotUploader` queue worker)

Plugin id `ssot_uploader`, runs on cron (`cron = {"time" = 60}`). Each item is:

```php
[
  'file_id'    => <fid>,
  'event_type' => 'monthly_labour_market_update' | 'industry_outlook_update' | 'labour_force_survey_update',
  'month'      => <int>,              // 1 for yearly uploads
  'year'       => <int>,
  'notes'      => <string>,           // commit message
  'uid'        => <uid>,              // commit author
  'date'       => 'Y/m/d H:i',        // spreadsheet timestamp
  'datasets'   => [['endpoint' => …, 'sheet' => …, 'range' => …], …],  // not yet consumed by the worker
]
```

`processItem()` uses the GitHub REST API (`X-GitHub-Api-Version: 2022-11-28`):

1. `GET /repos/{name}/contents/{path}/{filename}?ref={branch}` — fetch the current blob `sha`.
2. `PUT /repos/{name}/contents/{path}/{filename}` — create/update the file (`message` = notes).
3. `POST /repos/{name}/dispatches` — `repository_dispatch` with `event_type` and
   `client_payload = { branch, filename, year, month, date, notes }`.

On the `workbc-ssot` side, `.github/workflows/ssot-update.yml` currently listens only
for `monthly_labour_market_update`. Run the queue manually with `drush queue:run ssot_uploader`.

## Configuration

```php
$config['workbc']['ssot_url'] = 'https://<postgrest-host>/';

$config['workbc']['ssot_repo'] = [
  'name'      => 'bcgov/workbc-ssot',
  'path'      => 'migration/data',
  'branch'    => 'ams',
  'token'     => getenv('GITHUB_API_TOKEN'),
  'committer' => 'WorkBC SSoT Uploader',
];
```

Requirements: `phpoffice/phpspreadsheet`, the Drupal `file` module with a configured
private file system (`private://ssot/`), and a working cron.

## Database: `workbc_ssot_log`

One row per upload (not per dataset). Each insert sets `latest = 0` on all other rows
with the same `dataset_name` + `dataset_period`.

| Column | Meaning |
|--------|---------|
| `oid` | PK |
| `timestamp` | request time of the upload |
| `uid` | uploader |
| `dataset_name` | comma-separated SSoT endpoints written by this upload, e.g. `industry_outlook` or `labour_force_survey_industry,labour_force_survey_regional_employment,…` (single-dataset forms keep their historical value) |
| `dataset_period` | Unix timestamp of the 1st of the period (month or year) |
| `file_id` | `file_managed.fid` of the spreadsheet (made permanent) |
| `file_timestamp` | user-supplied spreadsheet timestamp |
| `notes` | free text |
| `latest` | 1 if this is the current upload for the period |

`workbc_ssot.views.inc` exposes the table to Views. The **SSoT upload log** view
(`config/sync/views.view.ssot_log.yml`) has an exposed *Dataset* drop-down backed by
`SsotDatasetFilter` (`plugin_id: workbc_ssot_dataset`):

- options are the distinct `endpoint` values of the SSoT `sources` API, merged with
  any names already in the log; cached for an hour under the `workbc_ssot_log` cache
  tag, which every upload invalidates;
- matching is per list element — `x` matches `dataset_name` equal to `x`, or starting
  with `x,`, ending with `,x`, or containing `,x,` (LIKE wildcards escaped); the
  *is none of* operator negates the same four conditions.

## Adding a new upload form

1. Create `src/Form/SsotUpload<Name>Form.php` extending `SsotUploadFormBase`.
2. Set `$id`, `$title`, `$label`, `$event_type`, `$period`, optionally `$author`,
   `$frontend_path`, `$repo_default`.
3. Write the `$toc`: one entry per SSoT endpoint. For tables, copy the CSV column list
   from the matching `workbc-ssot/migration/load/<endpoint>.load` file in order and take
   `range`/`sheet` from `migration/load/sources.csv`. Add `fill_down` for any columns the
   migrator runs through `csv_refill.php`.
4. Implement `getRouteName()`; add the route to `workbc_ssot.routing.yml` and links to
   `workbc_ssot.links.menu.yml` / `workbc_ssot.links.task.yml`.
5. Add the workbook and migrator CSVs to `tests/fixtures/` and extend the
   [tests](#tests); the payload must match `load/<endpoint>.csv` cell-by-cell.
6. In `workbc-ssot`, add a `repository_dispatch` type to a workflow for the new
   `event_type` (until then set `$repo_default = FALSE`).

## Tests

`tests/src/Unit/` holds a PHPUnit suite (no database or Drupal bootstrap needed):

| Test class | What it covers |
|---|---|
| `SsotUploadValidationTest` | every `type` rule (pass/fail/blank), `warn_only`, `same_sign`, `equal`, `sum` tolerance, the three `crosscheck_*` rules, cell/row/sheet addressing in messages, industry/region name normalisation and lookup rules |
| `SsotUploadFormBaseTest` | `buildContext` (month wrap, periods), `interpolate`, case-insensitive tab lookup, column derivation for tables (incl. `value` fields), range/field width mismatch, `fill_down`, N/A normalisation, formula evaluation, `prepareRows` casting and key resolution, and a declaration-consistency check of every shipped form's TOC |
| `SsotDatasetFilterTest` | option building from `sources` rows + logged names, `in`/`not in` WHERE conditions (via a mocked Views query), LIKE escaping |
| `SsotUploadWorkbookTest` | end-to-end regression against real workbooks in `tests/fixtures/`: LFS (4 datasets), Industry Profile and LMMU payloads are compared cell-by-cell with the CSVs the `workbc-ssot` migrator produced from the same files; also fault injection (unknown region/industry, bad numbers) |

Fixtures are the real published workbooks and the migrator's `load/*.csv` output copied
from `workbc-ssot/migration`. Lookups are fed from `tests/fixtures/industries.csv` and a
static region list via the `EngineAccess` test trait, which also exposes the protected
engine methods on `Test*Form` doubles.

The module ships its own [`phpunit.xml.dist`](./phpunit.xml.dist) (bootstrapped from
Drupal core, no DB/site required) and a Composer script. PHPUnit 11 needs PHP ≥ 8.3, so
run inside the docker-compose `php` service from the repository root:

```bash
docker compose exec php composer test:ssot                       # whole module suite
docker compose exec php vendor/bin/phpunit -c web/modules/custom/workbc_ssot --filter Workbook
docker compose exec php vendor/bin/phpunit -c web/core web/modules/custom/workbc_ssot   # via core config
```

(`docker compose run --rm --no-deps php composer test:ssot` works when the stack is
down.) On a host with PHP ≥ 8.3, the same commands run directly from `src/`.

When adding a form, drop its workbook and the migrator CSVs into `tests/fixtures/` and
add a case to `SsotUploadWorkbookTest` plus an entry in `shippedFormProvider()`.

## Known issues / TODO

- `SsotUploader::processItem()` calls `dsm($data)` (meaningless during cron) and emits
  several debug-level `notice` logs. It ignores the new `datasets` payload key.
- `ssot-update.yml` only handles `monthly_labour_market_update`; there are no workflows
  for `industry_outlook_update` or `labour_force_survey_update`, and the migrator
  script hard-codes `Sheet3`. A generic script driven by `client_payload.datasets` would
  let one workflow serve every form.
- Crosschecks against the previous period are only implemented for `record` datasets.
- Validation results are one message per cell; for large tables (LFS ≈ 1,700 checks)
  the confirmation screen is long even with successes hidden.
