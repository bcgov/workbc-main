<?php

namespace Drupal\workbc_ssot\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Drupal\Core\Datetime\DateHelper;
use Drupal\Core\Url;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Messenger\MessengerInterface;

const INDUSTRY_PROFILE_TAB = 'Sheet1';
const INDUSTRY_PROFILE_LABEL = 'B.C. Labour Market Outlook';
const INDUSTRY_PROFILE_DATA_ROWS = 18;
/**
* Class SsotUploadIndustryProfileForm.
*
* @package Drupal\workbc_ssot\Form
*/
class SsotUploadIndustryProfileForm extends ConfirmFormBase {
  use SsotUploadValidation;

  private $yearly_industry_profile_updates = null;
  private $previous_year = null;

  public $validations = [
    'industry' => ['cell' => 'A', 'type' => 'abs'],
    'openings' => ['cell' => 'B', 'type' => 'abs'],
    'share_total_employment_pct_first' => ['cell' => 'C', 'type' => 'pct'],
    'share_total_employment_pct_second' => ['cell' => 'D', 'type' => 'pct'],
    'share_total_employment_pct_third' => ['cell' => 'E', 'type' => 'pct'],
    'annual_employment_growth_rate_pct_first5y' => ['cell' => 'F', 'type' => 'pct'],
    'annual_employment_growth_rate_pct_second5y' => ['cell' => 'G', 'type' => 'pct'],
    'annual_employment_growth_rate_pct_10y' => ['cell' => 'H', 'type' => 'pct'],
  ];

  public $descriptions = [
    'abs' => 'Absolute value, positive, no decimals.',
    'pct' => 'Percentage value (0-100), positive, single decimal place.',
    'chg_abs' => 'Change absolute value (+/-), no decimals.',
    'chg_pct' => 'Change percentage (+/-) (0-50), single decimal place.',
    'same_sign' => 'Both values agree in numeric sign (+/-).',
    'blank' => 'A blank cell value will be shown as "Not available".',
    'sum' => 'The sum of the cell values matches the total value.',
    'equal' => 'Both values are equal.',
    'crosscheck_value' => 'The value matches the given cell\'s prior month value.',
    'crosscheck_change_abs' => 'The value matches the absolute difference between the given cell\'s current and prior month value.',
    'crosscheck_change_pct' => 'The value matches the percentage difference between the given cell\'s current and prior month value.',
  ];


//  /**
//   *
//   */
//   public function __construct(){
//     for ($row = 3; $row <= 20; $row++) {
//       $this->validations[] = [
//         'industry' => ['cell' => 'A'.$row, 'type' => 'abs'],
//         'openings' => ['cell' => 'B'.$row, 'type' => 'abs'],
//         'share_total_employment_pct_first' => ['cell' => 'C'.$row, 'type' => 'pct'],
//         'share_total_employment_pct_second' => ['cell' => 'D'.$row, 'type' => 'pct'],
//         'share_total_employment_pct_third' => ['cell' => 'E'.$row, 'type' => 'pct'],
//         'annual_employment_growth_rate_pct_first5y' => ['cell' => 'F'.$row, 'type' => 'pct'],
//         'annual_employment_growth_rate_pct_second5y' => ['cell' => 'G'.$row, 'type' => 'pct'],
//         'annual_employment_growth_rate_pct_10y' => ['cell' => 'H'.$row, 'type' => 'pct'],
//       ];
//     }
//     // dsm($this->validations);
//   }

  /**
   * {@inheritdoc}
   */
	public function getFormId()
  {
		return 'ssot_industry_profile_form';
	}

  /**
   * {@inheritdoc}
   */
  public function getDescription()
  {
    dsm($this->previous_year);
    dsm($this->yearly_industry_profile_updates);
    // $results = $this->validateRules($this->yearly_industry_profile_updates, $this->previous_year);
    // foreach ($results as $result) {
    //   \Drupal::messenger()->addMessage($result['message'], $result['result'] === 'success' ? MessengerInterface::TYPE_STATUS : MessengerInterface::TYPE_WARNING);
    // }
    return $this->t('This action will update the SSoT Labour Market Monthly Update for <strong>@year</strong>.', [
      '@year' => 2026, // $this->yearly_industry_profile_updates['year']
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion()
  {
    return $this->t('Are you sure you want to submit this update?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl()
  {
    return new Url('workbc_ssot.industryprofile');
  }

  /**
   * {@inheritdoc}
   */
	public function buildForm(array $form, FormStateInterface $form_state)
  {
    // Confirmation step.
    if ($form_state->get('confirmation')) {
      $this->yearly_industry_profile_updates = $form_state->get('yearly_industry_profile_updates');
      $parent_form = parent::buildForm($form, $form_state);
      $parent_form['toggle_warnings'] = [
        '#type' => 'checkbox',
        '#title' => t('Show successful validations'),
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

    $form['#attributes']['enctype'] = 'multipart/form-data';
    $form['source_file'] = [
      '#type' => 'managed_file',
      '#name' => 'industry_profile',
      '#title' => $this->t('Industry Profile Spreadsheet'),
      '#required' => true,
      '#description' => $this->t('Please upload your Industry Profile Yearly Update spreadsheet (Excel .xlsx format).'),
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
      '#default_value' => TRUE,
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
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
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
      $form_state->setErrorByName('industry_profile', $this->t('❌ This spreadsheet file is likely invalid. Please refer to the logs for more information.'));
      return;
    }

    // Look for Industry Profile tab in the spreadsheet.
    $sheet = array_filter($spreadsheet->getAllSheets(), function($sheet) {
      return strcasecmp($sheet->getTitle(), INDUSTRY_PROFILE_TAB) === 0;
    });
    if (empty($sheet)) {
      $form_state->setErrorByName('industry_profile', $this->t("❌ Tab \"@tab\" is not found. Please ensure that the tab containing Industry Profile information is called \"@tab\".", [
        '@tab' => INDUSTRY_PROFILE_TAB
      ]));
      return;
    }
    else {
      $sheet = reset($sheet);
    }

    // Get sheet date and compare to form date.
    try {
      $year = $sheet->getCell('C2')->getValue();
    }
    catch (\TypeError $e) {
      $form_state->setErrorByName('industry_profile', $this->t('❌ Invalid value in cell C2. Please check you are uploading the correct spreadsheet.'));
      return;
    }
    if ($year != $form_state->getValue('year')) {
      $form_state->setErrorByName('year', $this->t('❌ The selected year does not match the date in cell A3. Please check you are uploading the correct spreadsheet.'));
      return;
    }

    // Get the previous year dataset to compare some cells.
    $previous_year = $year - 1;

    // Fill the labour_outlook values.
    // @see https://github.com/bcgov/workbc-ssot/blob/master/migration/load/labour_outlook.load
    $yearly_industry_profile_updates = [
      'year' => $year,
    ];
    for ($i = 0; $i < INDUSTRY_PROFILE_DATA_ROWS; $i++) {
      $row = $i + 3;
      foreach ($this->validations as $key => $validation) {
        // Set the value. An explicit value overrides the cell value.
        if (array_key_exists('value', $validation)) {
          $value = $validation['value'];
        }
        else if (array_key_exists('cell', $validation)) {
          $value = $sheet->getCell($validation['cell'].$row)->getValue();
        }

        // Set the value.
        $yearly_industry_profile_updates['data'][$i][$key] = $value;
      }
    }

    // Retrieve only the errors in case of form validation.
    if (!$form_state->getValue('warn_only')) {
      // for ($i = 0; $i < INDUSTRY_PROFILE_DATA_ROWS; $i++) {
      $errors = array_column(array_filter($this->validateRules($yearly_industry_profile_updates['data'][0], $previous_year), function ($result) {
        return $result['result'] === 'error';
      }), 'message');
      // }
    }

    // Display errors if any.
    if (!empty($errors)) {
      foreach ($errors as $error) {
        \Drupal::messenger()->addError($error);
      }
      $form_state->setErrorByName('industry_profile', $this->t('Please re-upload the sheet once the errors above have been corrected.'));
      return;
    }

    // Good to go: Remember the value for submission.
    $form_state->set('yearly_industry_profile_updates', $yearly_industry_profile_updates);
    $form_state->set('crosscheck_value', $previous_year);
    $form_state->set('file_id', $file->id());
    $form_state->set('notes', $form_state->getValue('notes'));
    $form_state->set('timestamp', $form_state->getValue('timestamp')->getTimestamp());
    $form_state->set('repo', $form_state->getValue(('repo')));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    if (!$form_state->get('confirmation')) {
      $form_state->setRebuild(true);
      $form_state->set('confirmation', true);
      return;
    }

    $this->yearly_industry_profile_updates = $form_state->get('yearly_industry_profile_updates');
    $year = $this->yearly_industry_profile_updates['year'];
    $month = 12;

    $ssot_date = date('Y/m/d H:i', $form_state->get('timestamp'));
    $ssot_period = "$year/01/01 08:00";

    $this->ssot('industry_outlook', null, 'DELETE');

    try {
      $file = File::load($form_state->get('file_id'));

      $industries = array_column(querySSoT('industries'), NULL, 'key');

      $rows = [];
      foreach ($this->yearly_industry_profile_updates['data'] as $data) {
        $key = ssotIndustryKey($data['industry'], $industries);
        $data['industry'] = $key;
        $rows[] = $data;
      }
      $this->ssot('industry_outlook', null, 'POST', json_encode($rows));

      $data = json_encode([
        'period' => $ssot_period,
        'date' => $ssot_date,
        'filename' => $file->getFilename(),
        'endpoint' => 'industry_outlook',
        'sheet' => INDUSTRY_PROFILE_TAB ,
        'label' => INDUSTRY_PROFILE_LABEL
      ]);

      $check = json_decode($this->ssot("sources?endpoint=eq.industry_outlook")->getBody(), true);
      if (!$check) {
        $this->ssot('sources', null, 'POST', $data);
      }
      else {
        $this->ssot('sources?endpoint=eq.industry_outlook', null, 'PATCH', $data);
      }

      // Mark the file as permanent.
      $file->setPermanent();
      $file->save();

      // Save to the SSoT operations log.
      /** @var \Drupal\Core\Database\Connection $connection */
      $connection = \Drupal::service('database');
      $log_period = \DateTime::createFromFormat('d-m-Y H:i:s', "01-01-$year 00:00:00")->getTimestamp();
      $log_id = $connection->insert('workbc_ssot_log')
      ->fields([
        'uid' => \Drupal::currentUser()->id(),
        'timestamp' => \Drupal::time()->getRequestTime(),
        'dataset_name' => 'industry_outlook',
        'dataset_period' => $log_period,
        'file_id' => $file->id(),
        'file_timestamp' => $form_state->get('timestamp'),
        'notes' => $form_state->get('notes'),
      ])
      ->execute();
      $connection->update('workbc_ssot_log')
      ->fields([
        'latest' => 0,
      ])
      ->condition('dataset_name', 'industry_outlook')
      ->condition('dataset_period', $log_period)
      ->condition('oid', $log_id, '<>')
      ->execute();

      // Enqueue a job to update the SSoT repo.
      if ($form_state->get('repo')) {
        \Drupal::queue('ssot_uploader')->createItem([
          'file_id' => $form_state->get('file_id'),
          'event_type' => 'industry_outlook_update',
          'month' => $month,
          'year' => $year,
          'notes' => $form_state->get('notes'),
          'uid' => \Drupal::currentUser()->id(),
          'date' => $ssot_date,
        ]);
      }

      \Drupal::messenger()->addMessage(t('Industry Profile Yearly Update successfully updated for <strong>@year</strong>.', [
        '@year' => $year,
        '@month' => DateHelper::monthNames(true)[$month],
        '@url' => Url::fromUri('internal:/research-labour-market/bcs-economy/industry-profile-yearly-update', [
          'query' => ['month' => $month, 'year' => $year]
        ])->toString()
      ]));
      \Drupal::logger('workbc')->info(t('Industry Profile Yearly Update successfully updated for <strong>@year</strong> with file <a href="@uri">@filename</a>.', [
        '@year' => $year,
        '@month' => DateHelper::monthNames(true)[$month],
        '@uri' => \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()),
        '@filename' => $file->getFilename(),
      ]));

      \Drupal\Core\Cache\Cache::invalidateTags(['rendered']);
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError(t('❌ An error occurred while updating Industry Profiles. Please refer to the logs for more information.'));
      \Drupal::logger('workbc')->error($e->getMessage());
    }
  }
}
