<?php

namespace Drupal\workbc_ssot\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DateHelper;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Base class for SSoT spreadsheet upload forms.
 *
 * A concrete form declares a "table of contents" ($toc) mapping each SSoT
 * dataset (endpoint) to a sheet tab, a shape and a set of fields. The base
 * class takes care of the two-step confirmation flow, reading the workbook,
 * validating every dataset, writing to the SSoT API, maintaining `sources`,
 * logging the operation and queueing the repository update.
 *
 * TOC entry schema:
 * @code
 * '<endpoint>' => [
 *   'sheet'     => 'Sheet3',                 // tab title (case-insensitive)
 *   'shape'     => 'record' | 'table',       // scattered cells => 1 row, or rectangular range => N rows
 *   'range'     => 'A5:BC23',                // table only
 *   'write'     => 'replace'                 // DELETE all rows then POST
 *               | ['mode' => 'upsert', 'match' => 'year=eq.{year}&month=eq.{month}'],
 *   'sources'   => 'endpoint' | 'period',    // how to match the existing `sources` row (default by mode)
 *   'crosscheck'=> 'endpoint?year=eq.{prev_year}&month=eq.{prev_month}', // record only, optional
 *   'fill_down' => ['field', ...],           // table only: repeat the previous row's value for blank cells
 *   'fields'    => [                         // in column order for tables
 *     '<field>' => ['cell' => 'B3', 'type' => 'abs', ...rules],   // 'cell' is derived for tables
 *     '<field>' => ['value' => '{year}'],                         // literal / interpolated value
 *   ],
 * ]
 * @endcode
 */
abstract class SsotUploadFormBase extends ConfirmFormBase {
  use SsotUploadValidation;

  /**
   * Machine name of this upload, used for the form id.
   */
  protected string $id;

  /**
   * Human title used in messages, e.g. 'Labour Market Monthly Update'.
   */
  protected string $title;

  /**
   * Label written to `sources.label`. May contain {year} / {month}.
   */
  protected string $label;

  /**
   * Optional author written to `sources.author`.
   */
  protected ?string $author = null;

  /**
   * Period definition: granularity ('year' | 'month') and an optional check
   * cell ['sheet' => ..., 'cell' => ..., 'format' => 'excel_date' | 'year'].
   */
  protected array $period = ['granularity' => 'year'];

  /**
   * Table of contents: endpoint => dataset definition.
   */
  protected array $toc = [];

  /**
   * GitHub repository_dispatch event type sent to the workbc-ssot repo.
   */
  protected string $event_type;

  /**
   * Default state of the "Update SSoT repo" checkbox.
   */
  protected bool $repo_default = TRUE;

  /**
   * Optional internal path to the page displaying the data, for the success message.
   */
  protected ?string $frontend_path = null;

  /**
   * Cell contents treated as "not available".
   */
  protected array $not_available = ['', '-', '..', 'x', 'n/a', 'na', 'not available'];

  public $descriptions = [
    'abs' => 'Absolute value, positive, no decimals.',
    'num' => 'Numeric value, positive, up to two decimal places.',
    'pct' => 'Percentage value (0-100), positive, single decimal place.',
    'chg_abs' => 'Change absolute value (+/-), no decimals.',
    'chg_pct' => 'Change percentage (+/-) (0-50), single decimal place.',
    'industry' => 'A known SSoT industry name.',
    'region' => 'A known SSoT region name.',
    'same_sign' => 'Both values agree in numeric sign (+/-).',
    'blank' => 'A blank cell value will be shown as "Not available".',
    'sum' => 'The sum of the cell values matches the total value.',
    'equal' => 'Both values are equal.',
    'crosscheck_value' => 'The value matches the given cell\'s prior month value.',
    'crosscheck_change_abs' => 'The value matches the absolute difference between the given cell\'s current and prior month value.',
    'crosscheck_change_pct' => 'The value matches the percentage difference between the given cell\'s current and prior month value.',
  ];

  // Runtime state restored from form state on the confirmation step.
  protected ?array $datasets = null;
  protected ?array $crosschecks = null;
  protected ?array $context = null;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ssot_' . $this->id . '_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to submit this update?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute($this->getRouteName());
  }

  /**
   * Route name of this form, used for the cancel link.
   */
  abstract protected function getRouteName(): string;

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    foreach ($this->toc as $endpoint => $entry) {
      $sheet = count($this->toc) > 1 ? $entry['sheet'] : '';
      $rules = $this->fieldRules($entry);
      $crosscheck = $this->crosschecks[$endpoint] ?? null;
      $results = [];
      if ($entry['shape'] === 'table') {
        foreach ($this->datasets[$endpoint]['rows'] as $i => $row) {
          $results = array_merge($results, $this->validateRules($rules, $row, null, $this->datasets[$endpoint]['first_row'] + $i, $sheet));
        }
      }
      else {
        $results = $this->validateRules($rules, $this->datasets[$endpoint]['rows'][0], $crosscheck, null, $sheet);
      }
      foreach ($results as $result) {
        \Drupal::messenger()->addMessage($result['message'], $result['result'] === 'success' ? MessengerInterface::TYPE_STATUS : MessengerInterface::TYPE_WARNING);
      }
    }
    return $this->t('This action will update the SSoT dataset(s) <strong>@datasets</strong> for <strong>@period</strong>.', [
      '@datasets' => implode(', ', array_keys($this->toc)),
      '@period' => $this->periodLabel($this->context),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Confirmation step.
    if ($form_state->get('confirmation')) {
      $this->datasets = $form_state->get('datasets');
      $this->crosschecks = $form_state->get('crosschecks');
      $this->context = $form_state->get('context');
      $parent_form = parent::buildForm($form, $form_state);
      $parent_form['toggle_warnings'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show successful validations'),
        '#default_value' => false,
        '#attached' => ['library' => ['workbc_ssot/validation-toggle']]
      ];
      return $parent_form;
    }

    $form['year'] = [
      '#type' => 'number',
      '#title' => $this->t('Year'),
      '#required' => true,
      '#default_value' => idate('Y'),
      '#max' => idate('Y'),
      '#min' => idate('Y') - 5
    ];

    if ($this->period['granularity'] === 'month') {
      $form['month'] = [
        '#type' => 'select',
        '#title' => $this->t('Month'),
        '#required' => true,
        '#default_value' => idate('m'),
        '#options' => DateHelper::monthNames(true)
      ];
    }

    $form['#attributes']['enctype'] = 'multipart/form-data';
    $form['source_file'] = [
      '#type' => 'managed_file',
      '#name' => 'source_file',
      '#title' => $this->t('@title Spreadsheet', ['@title' => $this->title]),
      '#required' => true,
      '#description' => $this->t('Please upload your @title spreadsheet (Excel .xlsx format).', ['@title' => $this->title]),
      '#upload_validators' => ['FileExtension' => ['extensions' => 'xlsx']],
      '#upload_location' => 'private://ssot/',
    ];

    $form['timestamp'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Timestamp'),
      '#required' => true,
      '#default_value' => new DrupalDateTime(),
      '#date_year_range' => '2020:+0',
      '#description' => $this->t('Please provide the timestamp of the spreadsheet, as it appears in your File Explorer.'),
    ];

    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#description' => $this->t('Please provide any additional notes or comments, including the work ticket number. Limited to 250 characters.'),
      '#attributes' => [
        'maxlength' => 250,
      ]
    ];

    $form['warn_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ignore validation errors'),
      '#default_value' => FALSE,
      '#description' => $this->t('Check this box ON if you want to bypass all the validation rules, allowing potentially invalid sheets to be uploaded. In this case, validation rules will only appear as warnings on the confirmation screen.'),
    ];

    $form['repo'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Update SSoT repo'),
      '#default_value' => $this->repo_default,
      '#description' => $this->t('Check this box OFF if the <code>workbc-ssot</code> repo should NOT be updated with the spreadsheet you are uploading now. For example, if you are testing the upload functionality with a throwaway spreadsheet that should not be saved in the SSoT.'),
    ];

    $form['submit_upload'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit')
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Don't validate on ajax request.
    if (\Drupal::request()->request->get('_drupal_ajax')) {
      return;
    }

    // Don't validate on confirmation step.
    if ($form_state->get('confirmation')) {
      return;
    }

    // Don't validate on missing file.
    if (empty($form_state->getValue('source_file'))) {
      return;
    }
    $file = File::load(reset($form_state->getValue('source_file')));
    if (empty($file)) {
      return;
    }

    // Check that the file date is not in the future.
    if ($form_state->getValue('timestamp')->getTimestamp() > time()) {
      $form_state->setErrorByName('timestamp', $this->t('❌ The spreadsheet timestamp cannot be in the future. Please correct the date/time in this field.'));
      return;
    }

    // Check that the file is a spreadsheet.
    $path = \Drupal::service('file_system')->realpath($file->getFileUri());
    $name = basename($path);
    try {
      $spreadsheet = IOFactory::load($path);
    }
    catch (\Exception $e) {
      \Drupal::logger('workbc')->error('Error validating @name: @error', [
        '@name' => $name, '@error' => $e->getMessage()
      ]);
      $form_state->setErrorByName('source_file', $this->t('❌ This spreadsheet file is likely invalid. Please refer to the logs for more information.'));
      return;
    }

    // Build the period context from the form values.
    $context = $this->buildContext(
      intval($form_state->getValue('year')),
      $this->period['granularity'] === 'month' ? intval($form_state->getValue('month')) : null
    );

    // Check that all the tabs we need are present.
    $sheets = [];
    $needed = array_unique(array_merge(
      array_column($this->toc, 'sheet'),
      !empty($this->period['check']) ? [$this->period['check']['sheet']] : []
    ));
    foreach ($needed as $title) {
      $sheet = $this->findSheet($spreadsheet, $title);
      if (empty($sheet)) {
        $form_state->setErrorByName('source_file', $this->t("❌ Tab \"@tab\" is not found. Please ensure that the spreadsheet contains a tab called \"@tab\".", [
          '@tab' => $title
        ]));
        return;
      }
      $sheets[$title] = $sheet;
    }

    // Compare the sheet date (if any) with the form period.
    if (!empty($this->period['check'])) {
      $check = $this->period['check'];
      try {
        $value = $sheets[$check['sheet']]->getCell($check['cell'])->getCalculatedValue();
        if ($check['format'] === 'excel_date') {
          $date = ExcelDate::excelToDateTimeObject($value);
          $sheet_year = intval($date->format('Y'));
          $sheet_month = intval($date->format('n'));
        }
        else {
          if (!is_numeric($value)) {
            throw new \TypeError('Not a year');
          }
          $sheet_year = intval($value);
          $sheet_month = null;
        }
      }
      catch (\Throwable $e) {
        $form_state->setErrorByName('source_file', $this->t('❌ Invalid date in cell @cell. Please check you are uploading the correct spreadsheet.', [
          '@cell' => $check['cell']
        ]));
        return;
      }
      if ($sheet_year != $context['year']) {
        $form_state->setErrorByName('year', $this->t('❌ The selected year does not match the date in cell @cell. Please check you are uploading the correct spreadsheet.', [
          '@cell' => $check['cell']
        ]));
        return;
      }
      if (!is_null($context['month']) && !is_null($sheet_month) && $sheet_month != $context['month']) {
        $form_state->setErrorByName('month', $this->t('❌ The selected month does not match the date in cell @cell. Please check you are uploading the correct spreadsheet.', [
          '@cell' => $check['cell']
        ]));
        return;
      }
    }

    // Read and validate every dataset.
    $datasets = [];
    $crosschecks = [];
    $errors = [];
    foreach ($this->toc as $endpoint => $entry) {
      $sheet = $sheets[$entry['sheet']];
      $sheet_label = count($this->toc) > 1 ? $entry['sheet'] : '';
      try {
        $datasets[$endpoint] = $this->readDataset($sheet, $entry, $context);
      }
      catch (\Exception $e) {
        \Drupal::logger('workbc')->error('Error reading @endpoint from @name: @error', [
          '@endpoint' => $endpoint, '@name' => $name, '@error' => $e->getMessage()
        ]);
        $form_state->setErrorByName('source_file', $this->t('❌ Could not read dataset @endpoint from tab "@tab". Please refer to the logs for more information.', [
          '@endpoint' => $endpoint, '@tab' => $entry['sheet']
        ]));
        return;
      }

      if (!empty($entry['crosscheck'])) {
        $crosscheck = json_decode($this->ssot($this->interpolate($entry['crosscheck'], $context))->getBody(), true);
        $crosschecks[$endpoint] = !empty($crosscheck) ? reset($crosscheck) : null;
      }

      // Retrieve only the errors in case of form validation.
      if (!$form_state->getValue('warn_only')) {
        $rules = $this->fieldRules($entry);
        $results = [];
        if ($entry['shape'] === 'table') {
          foreach ($datasets[$endpoint]['rows'] as $i => $row) {
            $results = array_merge($results, $this->validateRules($rules, $row, null, $datasets[$endpoint]['first_row'] + $i, $sheet_label));
          }
        }
        else {
          $results = $this->validateRules($rules, $datasets[$endpoint]['rows'][0], $crosschecks[$endpoint] ?? null, null, $sheet_label);
        }
        $errors = array_merge($errors, array_column(array_filter($results, function ($result) {
          return $result['result'] === 'error';
        }), 'message'));
      }
    }

    // Display errors if any.
    if (!empty($errors)) {
      foreach ($errors as $error) {
        \Drupal::messenger()->addError($error);
      }
      $form_state->setErrorByName('source_file', $this->t('Please re-upload the sheet once the errors above have been corrected.'));
      return;
    }

    // Good to go: Remember the values for submission.
    $form_state->set('datasets', $datasets);
    $form_state->set('crosschecks', $crosschecks);
    $form_state->set('context', $context);
    $form_state->set('file_id', $file->id());
    $form_state->set('notes', $form_state->getValue('notes'));
    $form_state->set('timestamp', $form_state->getValue('timestamp')->getTimestamp());
    $form_state->set('repo', $form_state->getValue('repo'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->get('confirmation')) {
      $form_state->setRebuild(true);
      $form_state->set('confirmation', true);
      return;
    }

    $this->datasets = $form_state->get('datasets');
    $this->context = $context = $form_state->get('context');
    $year = $context['year'];
    $month = $context['month'] ?? 1;
    $ssot_date = date('Y/m/d H:i', $form_state->get('timestamp'));
    $ssot_period = $context['ssot_period'];
    $file = File::load($form_state->get('file_id'));
    $written = [];

    try {
      foreach ($this->toc as $endpoint => $entry) {
        $rows = $this->prepareRows($entry, $this->datasets[$endpoint]['rows']);
        $write = is_array($entry['write'] ?? null) ? $entry['write'] : ['mode' => $entry['write'] ?? 'replace'];

        if ($write['mode'] === 'upsert') {
          $match = $this->interpolate($write['match'], $context);
          $check = json_decode($this->ssot("$endpoint?$match")->getBody(), true);
          $payload = $entry['shape'] === 'table' ? $rows : $rows[0];
          if (empty($check)) {
            $this->ssot($endpoint, null, 'POST', json_encode($payload));
          }
          else {
            $this->ssot("$endpoint?$match", null, 'PATCH', json_encode($payload));
          }
        }
        else {
          $this->ssot($endpoint, null, 'DELETE');
          $this->ssot($endpoint, null, 'POST', json_encode($rows));
        }

        // Update the sources dataset.
        $source = [
          'period' => $ssot_period,
          'date' => $ssot_date,
          'filename' => $file->getFilename(),
          'endpoint' => $endpoint,
          'sheet' => $entry['sheet'],
          'range' => $entry['range'] ?? null,
          'label' => $this->interpolate($this->label, $context),
        ];
        if (!is_null($this->author)) {
          $source['author'] = $this->author;
        }
        $sources_match = $entry['sources'] ?? ($write['mode'] === 'upsert' ? 'period' : 'endpoint');
        $sources_query = "sources?endpoint=eq.$endpoint" . ($sources_match === 'period' ? "&period=eq.$ssot_period" : '');
        $check = json_decode($this->ssot($sources_query)->getBody(), true);
        if (empty($check)) {
          $this->ssot('sources', null, 'POST', json_encode($source));
        }
        else {
          $this->ssot($sources_query, null, 'PATCH', json_encode($source));
        }
        $written[] = $endpoint;
      }

      // Mark the file as permanent.
      $file->setPermanent();
      $file->save();

      // Save to the SSoT operations log.
      /** @var \Drupal\Core\Database\Connection $connection */
      $connection = \Drupal::service('database');
      $log_period = $context['log_period'];
      $dataset_name = implode(',', array_keys($this->toc));
      $log_id = $connection->insert('workbc_ssot_log')
      ->fields([
        'uid' => \Drupal::currentUser()->id(),
        'timestamp' => \Drupal::time()->getRequestTime(),
        'dataset_name' => $dataset_name,
        'dataset_period' => $log_period,
        'file_id' => $file->id(),
        'file_timestamp' => $form_state->get('timestamp'),
        'notes' => $form_state->get('notes') ?? '',
      ])
      ->execute();
      $connection->update('workbc_ssot_log')
      ->fields([
        'latest' => 0,
      ])
      ->condition('dataset_name', $dataset_name)
      ->condition('dataset_period', $log_period)
      ->condition('oid', $log_id, '<>')
      ->execute();

      // Enqueue a job to update the SSoT repo.
      if ($form_state->get('repo')) {
        \Drupal::queue('ssot_uploader')->createItem([
          'file_id' => $form_state->get('file_id'),
          'event_type' => $this->event_type,
          'month' => $month,
          'year' => $year,
          'notes' => $form_state->get('notes'),
          'uid' => \Drupal::currentUser()->id(),
          'date' => $ssot_date,
          'datasets' => array_map(function ($endpoint, $entry) {
            return ['endpoint' => $endpoint, 'sheet' => $entry['sheet'], 'range' => $entry['range'] ?? null];
          }, array_keys($this->toc), $this->toc),
        ]);
      }

      $period_label = $this->periodLabel($context);
      if (!empty($this->frontend_path)) {
        $query = ['year' => $year];
        if (!is_null($context['month'])) {
          $query['month'] = $month;
        }
        \Drupal::messenger()->addMessage($this->t('@title successfully updated for <strong>@period</strong>. <a href="@url">Click here</a> to see it!', [
          '@title' => $this->title,
          '@period' => $period_label,
          '@url' => Url::fromUri('internal:' . $this->frontend_path, ['query' => $query])->toString()
        ]));
      }
      else {
        \Drupal::messenger()->addMessage($this->t('@title successfully updated for <strong>@period</strong>.', [
          '@title' => $this->title,
          '@period' => $period_label,
        ]));
      }
      \Drupal::logger('workbc')->info('@title successfully updated for <strong>@period</strong> (@datasets) with file <a href="@uri">@filename</a>.', [
        '@title' => $this->title,
        '@period' => $period_label,
        '@datasets' => implode(', ', $written),
        '@uri' => \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()),
        '@filename' => $file->getFilename(),
      ]);

      \Drupal\Core\Cache\Cache::invalidateTags(['rendered', 'workbc_ssot_log']);
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($this->t('❌ An error occurred while updating @title. Datasets already written: @written. Please refer to the logs for more information.', [
        '@title' => $this->title,
        '@written' => empty($written) ? $this->t('none') : implode(', ', $written),
      ]));
      \Drupal::logger('workbc')->error($e->getMessage());
    }
  }

  /**
   * Build the period context used for interpolation and persistence.
   */
  protected function buildContext(int $year, ?int $month): array {
    $context = [
      'year' => $year,
      'month' => $month,
      'prev_year' => is_null($month) ? $year - 1 : ($month == 1 ? $year - 1 : $year),
      'prev_month' => is_null($month) ? null : ($month == 1 ? 12 : $month - 1),
    ];
    $m = str_pad(strval($month ?? 1), 2, '0', STR_PAD_LEFT);
    $context['ssot_period'] = "$year/$m/01 08:00";
    $context['log_period'] = \DateTime::createFromFormat('d-m-Y H:i:s', "01-$m-$year 00:00:00")->getTimestamp();
    return $context;
  }

  /**
   * Human-readable period, e.g. "May 2026" or "2026".
   */
  protected function periodLabel(array $context): string {
    if (!is_null($context['month'])) {
      return DateHelper::monthNames(true)[$context['month']] . ' ' . $context['year'];
    }
    return strval($context['year']);
  }

  /**
   * Replace {year}, {month}, {prev_year}, {prev_month} placeholders.
   */
  protected function interpolate(?string $template, array $context): ?string {
    if (is_null($template)) {
      return null;
    }
    return preg_replace_callback('/\{(\w+)\}/', function ($m) use ($context) {
      return array_key_exists($m[1], $context) ? strval($context[$m[1]]) : $m[0];
    }, $template);
  }

  /**
   * Find a worksheet by title, case-insensitively.
   */
  protected function findSheet(Spreadsheet $spreadsheet, string $title): ?Worksheet {
    foreach ($spreadsheet->getAllSheets() as $sheet) {
      if (strcasecmp($sheet->getTitle(), $title) === 0) {
        return $sheet;
      }
    }
    return null;
  }

  /**
   * Field rules for a TOC entry, with 'cell' derived from the range for tables.
   */
  protected function fieldRules(array $entry): array {
    $fields = $entry['fields'];
    if (($entry['shape'] ?? 'record') === 'table') {
      [[$col_start, $row_start], [$col_end, $row_end]] = Coordinate::rangeBoundaries($entry['range']);
      $columns = $col_end - $col_start + 1;
      $offset = 0;
      foreach ($fields as $key => &$rule) {
        if (array_key_exists('value', $rule)) {
          continue;
        }
        // Column offset within the range, used to pick the value out of each row.
        $rule['_col'] = $offset;
        if (!array_key_exists('cell', $rule)) {
          $rule['cell'] = Coordinate::stringFromColumnIndex($col_start + $offset);
        }
        $offset++;
      }
      unset($rule);
      if ($offset !== $columns) {
        throw new \LogicException(sprintf('Range %s has %d columns but %d cell fields are defined.', $entry['range'], $columns, $offset));
      }
    }
    return $fields;
  }

  /**
   * Read a dataset from the worksheet.
   *
   * @return array
   *   ['rows' => list of field => value rows, 'first_row' => sheet row number of the first row]
   */
  protected function readDataset(Worksheet $sheet, array $entry, array $context): array {
    $rules = $this->fieldRules($entry);

    if (($entry['shape'] ?? 'record') === 'table') {
      [[, $row_start], [, $row_end]] = Coordinate::rangeBoundaries($entry['range']);
      $values = $sheet->rangeToArray($entry['range'], null, true, false, false);
      $rows = [];
      $previous = [];
      foreach ($values as $cells) {
        $row = [];
        foreach ($rules as $key => $rule) {
          $row[$key] = array_key_exists('value', $rule)
            ? $this->interpolate($rule['value'], $context)
            : $this->normalizeValue($cells[$rule['_col']] ?? null);
        }
        foreach ($entry['fill_down'] ?? [] as $key) {
          if (is_null($row[$key]) && array_key_exists($key, $previous)) {
            $row[$key] = $previous[$key];
          }
        }
        $previous = $row;
        $rows[] = $row;
      }
      return ['rows' => $rows, 'first_row' => (int) $row_start];
    }

    $row = [];
    foreach ($rules as $key => $rule) {
      if (array_key_exists('value', $rule)) {
        $row[$key] = $this->interpolate($rule['value'], $context);
      }
      else {
        $row[$key] = $this->normalizeValue($sheet->getCell($rule['cell'])->getCalculatedValue());
      }
    }
    return ['rows' => [$row], 'first_row' => null];
  }

  /**
   * Convert "not available" markers to NULL and trim strings.
   */
  protected function normalizeValue($value) {
    if (is_null($value)) {
      return null;
    }
    if (is_string($value)) {
      $value = trim($value);
      if (in_array(strtolower($value), $this->not_available, true)) {
        return null;
      }
    }
    return $value;
  }

  /**
   * Convert the read rows into API payload rows: resolve lookups to keys and
   * cast numeric strings to numbers.
   */
  protected function prepareRows(array $entry, array $rows): array {
    $rules = $this->fieldRules($entry);
    $prepared = [];
    foreach ($rows as $row) {
      $out = [];
      foreach ($rules as $key => $rule) {
        $value = $row[$key] ?? null;
        $type = $rule['type'] ?? null;
        if (in_array($type, ['industry', 'region'])) {
          $value = $this->resolveLookup($type, $value, $rule) ?? $value;
        }
        elseif (!is_null($type) && is_numeric($value)) {
          $value = $value + 0;
        }
        $out[$key] = $value;
      }
      $prepared[] = $out;
    }
    return $prepared;
  }
}
