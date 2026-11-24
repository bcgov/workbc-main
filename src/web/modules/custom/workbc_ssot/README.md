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
| [`workbc_ssot.routing.yml`](./workbc_ssot.routing.yml) | Admin routes under `/admin/config/workbc/ssot/*`. |

Routes:

| Route | Path | Page / Form |
|-------|------|------|
| `view.ssot_log.page_1` | `/admin/config/workbc/ssot/log` | Upload log (main SSoT menu page) |
| `workbc_ssot.uploads` | `/admin/config/workbc/ssot/upload` | Links to the upload forms, using Drupal's standard admin menu listing |
| `workbc_ssot.monthly_labour_market_updates` | `/admin/config/workbc/ssot/monthly-labour-market-updates` | `SsotUploadLmmuForm` |
| `workbc_ssot.industry_outlook` | `/admin/config/workbc/ssot/industry-outlook` | `SsotUploadIndustryProfileForm` |
| `workbc_ssot.labour_force_survey` | `/admin/config/workbc/ssot/labour-force-survey` | `SsotUploadLabourForceSurveyForm` |
| `workbc_ssot.refresh` | `/admin/config/workbc/ssot/refresh` | `SsotRefreshForm` (Refresh local version of SSoT datasets) |

The **Configuration > WorkBC > SSoT** menu opens the upload log. Its primary
tabs are **Upload Log**, **Upload Datasets**, and **Refresh Datasets**. A separate
**SSoT: Upload Datasets** menu under WorkBC gives users without log access a
route to the upload forms. Its landing page lists the permitted forms and their
descriptions. Import the view configuration and rebuild caches after changing
navigation.

Each upload form has an `upload ssot data sheet <id>` permission, labelled with
its `$title`. Users with `administer ssot settings` can use all forms and access
the upload log and refresh page; a sheet-specific permission grants only that
form and the Upload Datasets landing page. The landing page discovers sheet
permissions from Drupal's registered permissions, so its route does not need
updating for each new sheet. Users also need Drupal's `access
administration pages` permission to navigate the administrative menu. Rebuild
caches after adding or changing permissions or routes.

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
            │  1. Read existing workbook SHA, if present                   │
            │  2. Upload workbook through the Contents API                 │
            │  3. Dispatch ssot_update with workbook SHA and metadata       │
            └──────────────────────────────┬───────────────────────────────┘
                                           ▼
            ┌──────────────────────────────────────────────────────────────┐
            │ workbc-ssot/.github/workflows/ssot-update.yml                 │
            │  checkout → verify workbook → register metadata              │
            │  run migrator → pg_dump → commit metadata + snapshot          │
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

### Industry Profile Yearly Update

- Granularity `year`; period check: `Sheet1!C2` as a plain year.
- One `table`-shaped dataset `industry_outlook`, range `Sheet1!A3:H20` (18 industries ×
  8 columns). `industry` is a lookup column resolved to the SSoT industry key.
- Write: `replace` (the table is a snapshot with no year column).

### Labour Force Survey Yearly Update

- Granularity `year`; **no period check** — the workbook has no reliable date cell, so
  the form's year is trusted. `repo` defaults to **off**; when enabled, the generic
  workflow ingests all four registered datasets from this workbook.
- Four `table` datasets across two tabs, ranges copied from `sources.range`:

  | Dataset | Tab | Range | Key column |
  |---|---|---|---|
  | `labour_force_survey_industry` | Industry Profiles | `A5:BC23` | `industry` (exception: `Total, All Industries`) |
  | `labour_force_survey_regional_employment` | Regional Profiles | `A6:V13` | `region` |
  | `labour_force_survey_regional_industry_region` | Regional Profiles | `A21:AK28` | `region` |
  | `labour_force_survey_regional_industry_province` | Regional Profiles | `A35:U42` | `region` |

- Cell-backed field lists mirror the CSV column lists in the corresponding
  `workbc-ssot/migration/load/*.load` files. Types were inferred from column names
  (`_num`→`abs`, `_pct`/rates/shares→`pct`, `yoy_*`→`chg_*`, `earnings_*`→`num`).
- Regional employment also computes `total_employment_num`,
  `part_time_employment_pct` and `part_time_employment_num` from the full-time
  count and percentage, matching the loader's post-load formulas. Missing
  operands propagate NULL; a zero full-time percentage with a provided count,
  or a nonnumeric operand, stops the upload before writing. PHP floating-point
  results can differ slightly from PostgreSQL `numeric`.
- `fill_down` replicates `csv_refill.php` for the vertically merged "B.C. average"
  columns on the Industry Profiles tab.
- In `regional_industry_province`, `Regional Profiles!B35:S35` is expected to be
  blank for British Columbia. These cells pass via `blank_if`; populated values
  are errors. Columns T-U (`goods`, `services`) retain ordinary percentage validation.
- Write: `replace` for all four; `sources` rows are matched by endpoint.

## `SsotUploadFormBase`: the TOC-driven engine

### Form-level declarations

```php
class SsotUploadFooForm extends SsotUploadFormBase {
  protected string  $id            = 'foo';                  // form id ssot_foo_form
  protected string  $title         = 'Foo Update';           // UI messages
  protected string  $label         = '{year} Foo Survey';    // sources.label (placeholders allowed)
  protected ?string $author        = 'BC LMIO';              // sources.author (optional)
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
    '<field>' => ['type' => 'synthetic', 'callback' => 'methodName'], // computed, consumes no column
    '<placeholder>' => ['type' => 'ignore'],                    // table only: skip one column
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

For tables, an `ignore` field consumes one column in the range but does not
appear in the row, validation results, synthetic callback input, or API payload.
Give each ignored column a distinct placeholder key. Its rule accepts only
`type` and cannot be used in `fill_down`. Do not reference it from other fields.

### Synthetic fields

A synthetic field names a callable method on the form. After all source and
literal values are read and normalized (and table `fill_down` is applied), the
engine passes the complete source row to each callback. Callbacks return a
scalar or `NULL`; non-finite floats and non-scalar results fail explicitly.
They do not receive other synthetic values, so their order does not affect the
result. Synthetic fields consume no worksheet columns, have no cell validation
messages, and are included in the prepared API payload.

The rule accepts only `type` and `callback`. Missing or inaccessible methods,
additional rule keys, and callback failures stop the upload before confirmation
or writing, even when validation errors are ignored. Callback failures identify
the field and table row in the logs. Validate source inputs in callbacks when
needed and keep calculations consistent with the corresponding SSoT loader.

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
| `'blank_if' => ['field' => 'region', 'value' => 'british_columbia']` | this cell must be blank when the referenced field equals the given value |
| `'sum' => [['a','b'], ['c','d']]` | each group sums to this cell (±100) |
| `'crosscheck_value' => 'other'` | equals the prior period's `other` |
| `'crosscheck_change_abs' => 'other'` | equals `other_now − other_prev` |
| `'crosscheck_change_pct' => 'other'` | equals `round((now−prev)·100/now, 1)` |
| `'warn_only' => ['sum', …]` | downgrade the listed rules from error to warning |

Every rule defaults to `error`. In `validateForm()` only errors block submission (unless
*Ignore validation errors* is checked).

`blank_if` compares strictly against the referenced field's value, resolving
`industry`/`region` lookups to their canonical SSoT keys regardless of field order.
When the condition matches, a normalized `NULL` produces a success instead of the
usual blank warning; any nonblank value (including zero or invalid text) is an
error. `warn_only => ['blank_if']` downgrades that violation to a warning.
When the condition does not match, existing validation applies unchanged.
The rule never changes the data. A malformed condition or missing referenced
field raises a `LogicException`. Blankness uses the reader's existing normalization
(including N/A markers); it is checked before numeric coercion.

## Background job (`SsotUploader` queue worker)

Plugin id `ssot_uploader`, runs on cron (`cron = {"time" = 60}`). Each item is:

```php
[
  'file_id'    => <fid>,
  'month'      => <int>,              // 1 for yearly uploads
  'year'       => <int>,
  'notes'      => <string>,           // commit message
  'uid'        => <uid>,              // commit author
  'date'       => 'Y/m/d H:i',        // spreadsheet timestamp
  'label'      => <string>,           // interpolated source label
  'author'     => <string|null>,      // optional source author
  'datasets'   => [['endpoint' => …, 'sheet' => …, 'range' => …, 'match' => 'endpoint'|'period'], …],
]
```

`processItem()` uses the GitHub REST API (`X-GitHub-Api-Version: 2022-11-28`):

1. Read the workbook's current SHA using the Contents API. Only a 404 means a new
   file; other errors propagate for queue retry.
2. Upload the workbook using the Contents API (`message` = notes), passing its
   current SHA when replacing it. The client does not read or edit the manifest.
3. `POST /repos/{name}/dispatches` — `repository_dispatch` with `event_type=ssot_update` and
   `client_payload = { branch, filename, workbook_sha, year, month, date, notes,
   metadata: { label, author, datasets } }`. The SHA identifies the uploaded Git
   blob, not a commit. Upload failures prevent dispatch.

On the `workbc-ssot` side, `.github/workflows/ssot-update.yml` reads the dispatch
JSON through `GITHUB_EVENT_PATH`. Its `register_sources.php` command verifies the
workbook SHA, updates `migration/load/sources.csv`, then the workflow invokes
`php src/ssot_ingest.php --file "$SOURCE_FILENAME"`. Metadata registration and replacement
rules belong entirely to that repository. After successful ingestion, the
manifest, generated CSVs and snapshots are committed together with a non-forced
push. A stale workbook or concurrent publication fails the workflow and requires
an explicit retry; accepting the dispatch does not mean ingestion has succeeded.
Deploy the backend registration command/workflow before enabling this worker.
Run the queue manually with `drush queue:run ssot_uploader`.

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
uses `SsotDatasetPeriod` for its *Period* column: Industry Outlook and the four LFS
datasets display only the year, including comma-separated uploads containing only
those yearly datasets. Monthly LMMU uploads retain the month and year (including
January). Unknown or mixed-granularity datasets retain the configured date format.
New yearly endpoints must be added to the handler's `YEARLY_DATASETS` list.

The view
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
2. Set `$id`, `$title`, `$label`, `$period`, optionally `$author`,
   `$frontend_path`, `$repo_default`.
3. Write the `$toc`: one entry per SSoT endpoint. For tables, copy the CSV column list
   from the matching `workbc-ssot/migration/load/<endpoint>.load` file in order and take
   `range`/`sheet` from `migration/load/sources.csv`. Add `fill_down` for any columns the
   migrator runs through `csv_refill.php`.
4. Implement `getRouteName()`; add the route to `workbc_ssot.routing.yml` with
   `_custom_access: '\Drupal\workbc_ssot\Form\SsotUpload<Name>Form::access'` and a
   menu link under `workbc_ssot.uploads` in `workbc_ssot.links.menu.yml`. Define
   `upload ssot data sheet <id>` in `workbc_ssot.permissions.yml` with the title
   `Upload data sheet <title>`. The base class grants route
   access to that permission or `administer ssot settings`. No new tab is needed.
5. Add the workbook and migrator CSVs to `tests/fixtures/` and extend the
   [tests](#tests); the payload must match `load/<endpoint>.csv` cell-by-cell.
6. In `workbc-ssot`, provide the endpoint's loader and any source-specific recipe.
   The existing generic dispatch handles the workbook; no new workflow job or
   dataset-specific conversion step is required.

## Tests

`tests/src/Unit/` holds a PHPUnit suite (no database or Drupal bootstrap needed):

| Test class | What it covers |
|---|---|
| `SsotUploadAccessTest` | upload permission isolation, administrator access, landing page and route declarations |
| `SsotUploadValidationTest` | every `type` rule (pass/fail/blank), `warn_only`, `same_sign`, `equal`, `sum` tolerance, the three `crosscheck_*` rules, cell/row/sheet addressing in messages, industry/region name normalisation and lookup rules |
| `SsotUploadFormBaseTest` | `buildContext` (month wrap, periods), `interpolate`, case-insensitive tab lookup, column derivation for tables (incl. `value` fields), range/field width mismatch, `fill_down`, N/A normalisation, formula evaluation, `prepareRows` casting and key resolution, and a declaration-consistency check of every shipped form's TOC |
| `SsotDatasetFilterTest` | option building from `sources` rows + logged names, `in`/`not in` WHERE conditions (via a mocked Views query), LIKE escaping |
| `SsotUploadWorkbookTest` | end-to-end regression against real workbooks in `tests/fixtures/`: LFS (4 datasets), Industry Profile and LMMU payloads are compared cell-by-cell with the CSVs the `workbc-ssot` migrator produced from the same files; also fault injection (unknown region/industry, bad numbers) |
| `SsotUploaderTest` | Contents API upload/create, generic dispatch with dataset metadata and workbook identity, and failure propagation without network calls |

Fixtures are the real published workbooks and the migrator's `load/*.csv` output copied
from `workbc-ssot/migration`. Lookups are fed from `tests/fixtures/industries.csv` and a
static region list via the `EngineAccess` test trait, which also exposes the protected
engine methods on `Test*Form` doubles.

The module ships its own [`phpunit.xml.dist`](./phpunit.xml.dist) (bootstrapped from
Drupal core, no DB/site required) and a Composer script. PHPUnit 11 needs PHP ≥ 8.3, so
run inside the docker-compose `php` service from the repository root:

```bash
docker compose exec php composer test:ssot                       # unit suite
docker compose exec php vendor/bin/phpunit -c web/modules/custom/workbc_ssot --filter Workbook
docker compose exec php vendor/bin/phpunit -c web/core web/modules/custom/workbc_ssot   # via core config
```

(`docker compose run --rm --no-deps php composer test:ssot` works when the stack is
down.) On a host with PHP ≥ 8.3, the same commands run directly from `src/`.

When adding a form, drop its workbook and the migrator CSVs into `tests/fixtures/` and
add a case to `SsotUploadWorkbookTest` plus an entry in `shippedFormProvider()`.

### Logged-in permission tests

[`SsotUploadPermissionsTest`](./tests/src/Functional/SsotUploadPermissionsTest.php)
uses Drupal's `BrowserTestBase` and real logins, not mocked accounts. Each
sheet-only user has exactly one permission and can open the upload landing page,
see and follow only their sheet link, and open that upload form. Other sheets,
the upload log, refresh page, and WorkBC administration page return HTTP 403.
The suite also covers logged-in users without upload permissions and users with
`administer ssot settings`. It imports the actual log view and tests local tabs;
cached dataset options keep the tests independent of the external SSoT API.

These functional tests run separately from the database-free unit suite. No
separate database server or Drupal installation is needed: SQLite stores the
temporary test databases in files shared with the web server.

For this repository's Compose stack, prepare the test directory from the
repository root:

```bash
docker compose exec php sh -c '
  mkdir -p web/sites/simpletest
  chgrp www-data web/sites/simpletest
  chmod 2775 web/sites/simpletest
  touch web/sites/simpletest/ssot-tests.sqlite
  chgrp www-data web/sites/simpletest/ssot-tests.sqlite
  chmod 0664 web/sites/simpletest/ssot-tests.sqlite
'
```

Run PHPUnit as the PHP-FPM worker account (`www-data`), not the container's
default CLI account (`wodby`):

```bash
docker compose exec --user www-data \
  -e SIMPLETEST_BASE_URL=http://nginx \
  -e SIMPLETEST_DB='sqlite://localhost//var/www/html/web/sites/simpletest/ssot-tests.sqlite' \
  -e BROWSERTEST_OUTPUT_DIRECTORY=/tmp \
  php vendor/bin/phpunit -c web/core \
  web/modules/custom/workbc_ssot/tests/src/Functional
```

CLI and HTTP requests must run as the same OS user so both can write the generated
SQLite databases, journals and test-site files. Otherwise the login page can
fail with a database error, reported by the test as a missing "Log in" button.
If previous runs created HTML logs under another user, their directory and
counter file must also be writable by `www-data`; alternatively pass
`-e BROWSERTEST_OUTPUT_DIRECTORY=` to disable HTML logging.

Outside Compose, use PHP 8.3 or newer, a reachable web server, and a shared,
absolute SQLite path in `SIMPLETEST_DB`. Never point these tests at the site's
production database. Drupal creates temporary test sites and cleans them up.
The existing log view's schema gaps are excluded from schema checking for these
access tests; unrelated Drupal/module deprecation notices may still be reported.

## Known issues / TODO

- Crosschecks against the previous period are only implemented for `record` datasets.
- Validation results are one message per cell; for large tables (LFS ≈ 1,700 checks)
  the confirmation screen is long even with successes hidden.
