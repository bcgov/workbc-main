<?php

namespace Drupal\workbc_custom\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Drupal\Core\Datetime\DateHelper;
use Drupal\Core\Url;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// @see https://stackoverflow.com/a/2430144/209184
function number_precision($value) {
  return strlen(substr(strrchr($value, '.'), 1));
}

function is_number_integer($value) {
  return abs($value - round($value)) <= PHP_FLOAT_EPSILON;
}

function array_key_push(&$array, $key, $value) {
  if (array_key_exists($key, $array)) {
    $array[$key][] = $value;
  }
  else {
    $array[$key] = [$value];
  }
}

/**
* Class SsotUploadLmmuForm.
*
* @package Drupal\workbc_custom\Form
*/
class SsotUploadLmmuForm extends ConfirmFormBase {
  private $monthly_labour_market_updates = NULL;

  public $validations = [
    'year' => ['value' => ['form_state_key' => 'year'], 'cell' => 'A3', 'type' => 'date_year'],
    'month' => ['value' => ['form_state_key' => 'month'], 'cell' => 'A3', 'type' => 'date_month'],

    'employment_by_age_group_15_24' => ['cell' => 'C8', 'type' => 'abs'],
    'employment_by_age_group_25_54' => ['cell' => 'C9', 'type' => 'abs'],
    'employment_by_age_group_55' => ['cell' => 'C10', 'type' => 'abs'],
    'employment_by_age_group_15_24_previous' => ['cell' => 'B8', 'type' => 'abs', 'previous_month' => 'employment_by_age_group_15_24'],
    'employment_by_age_group_25_54_previous' => ['cell' => 'B9', 'type' => 'abs', 'previous_month' => 'employment_by_age_group_25_54'],
    'employment_by_age_group_55_previous' => ['cell' => 'B10', 'type' => 'abs', 'previous_month' => 'employment_by_age_group_55'],

    'employment_by_gender_women' => ['cell' => 'C12', 'type' => 'abs'],
    'employment_by_gender_men' => ['cell' => 'C13', 'type' => 'abs'],
    'employment_by_gender_women_previous' => ['cell' => 'B12', 'type' => 'abs', 'previous_month' => 'employment_by_gender_women'],
    'employment_by_gender_men_previous' => ['cell' => 'B13', 'type' => 'abs', 'previous_month' => 'employment_by_gender_men'],

    'total_unemployed' => ['cell' => 'B37', 'type' => 'abs'],
    'total_unemployed_previous' => ['cell' => 'A37', 'type' => 'abs', 'previous_month' => 'total_unemployed'],
    'total_employed' => ['cell' => 'B3', 'type' => 'abs', 'sum' => [
      ['employment_by_age_group_15_24', 'employment_by_age_group_25_54', 'employment_by_age_group_55'],
      ['employment_by_gender_women', 'employment_by_gender_men']
    ]],

    'employment_change_pct_full_time_jobs' => ['cell' => 'B19', 'type' => 'chg_pct'],
    'employment_change_abs_full_time_jobs' => ['cell' => 'C19', 'type' => 'chg_abs', 'same_sign' => 'employment_change_pct_full_time_jobs'],
    'employment_change_pct_part_time_jobs' => ['cell' => 'B20', 'type' => 'chg_pct'],
    'employment_change_abs_part_time_jobs' => ['cell' => 'C20', 'type' => 'chg_abs', 'same_sign' => 'employment_change_pct_part_time_jobs'],
    'employment_change_pct_total_employment' => ['cell' => 'B18', 'type' => 'chg_pct'],
    'employment_change_abs_total_employment' => ['cell' => 'C18', 'type' => 'chg_abs', 'same_sign' => 'employment_change_pct_total_employment', 'sum' => [
      ['employment_change_abs_full_time_jobs', 'employment_change_abs_part_time_jobs']
    ]],

    'employment_rate_change_pct_unemployment' => ['cell' => 'B22', 'type' => 'chg_pct'],
    'employment_rate_pct_unemployment' => ['cell' => 'C22', 'type' => 'pct'],
    'employment_rate_change_pct_participation' => ['cell' => 'B23', 'type' => 'chg_pct'],
    'employment_rate_pct_participation' => ['cell' => 'C23', 'type' => 'pct'],
    'employment_rate_change_pct_unemployment_previous' => ['cell' => 'E22', 'type' => 'chg_pct'],
    'employment_rate_pct_unemployment_previous' => ['cell' => 'F22', 'type' => 'pct'],
    'employment_rate_change_pct_participation_previous' => ['cell' => 'E23', 'type' => 'chg_pct'],
    'employment_rate_pct_participation_previous' => ['cell' => 'F23', 'type' => 'pct'],

    'population_british_columbia' => ['cell' => 'B26', 'type' => 'abs'],
    'population_vancouver_island_coast' => ['cell' => 'B27', 'type' => 'abs'],
    'population_mainland_southwest' => ['cell' => 'B28', 'type' => 'abs'],
    'population_thompson_okanagan' => ['cell' => 'B29', 'type' => 'abs'],
    'population_kootenay' => ['cell' => 'B30', 'type' => 'abs'],
    'population_cariboo' => ['cell' => 'B31', 'type' => 'abs'],
    'population_north_coast_nechako' => ['cell' => 'B32', 'type' => 'abs'],
    'population_northeast' => ['cell' => 'B33', 'type' => 'abs'],

    'unemployment_pct_british_columbia' => ['cell' => 'B41', 'type' => 'pct'],
    'unemployment_pct_british_columbia_previous' => ['cell' => 'E41', 'type' => 'pct'],
    'total_jobs_british_columbia' => ['cell' => 'C41', 'type' => 'abs'],
    'unemployment_pct_vancouver_island_coast' => ['cell' => 'B42', 'type' => 'pct'],
    'unemployment_pct_vancouver_island_coast_previous' => ['cell' => 'E42', 'type' => 'pct'],
    'total_jobs_vancouver_island_coast' => ['cell' => 'C42', 'type' => 'abs'],
    'unemployment_pct_mainland_southwest' => ['cell' => 'B43', 'type' => 'pct'],
    'unemployment_pct_mainland_southwest_previous' => ['cell' => 'E43', 'type' => 'pct'],
    'total_jobs_mainland_southwest' => ['cell' => 'C43', 'type' => 'abs'],
    'unemployment_pct_thompson_okanagan' => ['cell' => 'B44', 'type' => 'pct'],
    'unemployment_pct_thompson_okanagan_previous' => ['cell' => 'E44', 'type' => 'pct'],
    'total_jobs_thompson_okanagan' => ['cell' => 'C44', 'type' => 'abs'],
    'unemployment_pct_kootenay' => ['cell' => 'B45', 'type' => 'pct'],
    'unemployment_pct_kootenay_previous' => ['cell' => 'E45', 'type' => 'pct'],
    'total_jobs_kootenay' => ['cell' => 'C45', 'type' => 'abs'],
    'unemployment_pct_cariboo' => ['cell' => 'B46', 'type' => 'pct'],
    'unemployment_pct_cariboo_previous' => ['cell' => 'E46', 'type' => 'pct'],
    'total_jobs_cariboo' => ['cell' => 'C46', 'type' => 'abs'],
    'unemployment_pct_north_coast_nechako' => ['cell' => 'B47', 'type' => 'pct'],
    'unemployment_pct_north_coast_nechako_previous' => ['cell' => 'E47', 'type' => 'pct'],
    'total_jobs_north_coast_nechako' => ['cell' => 'C47', 'type' => 'abs'],
    'unemployment_pct_northeast' => ['cell' => 'B48', 'type' => 'pct'],
    'unemployment_pct_northeast_previous' => ['cell' => 'E48', 'type' => 'pct'],
    'total_jobs_northeast' => ['cell' => 'C48', 'type' => 'abs'],

    'industry_pct_accommodation_food_services' => ['cell' => 'B59', 'type' => 'chg_pct'],
    'industry_abs_accommodation_food_services' => ['cell' => 'C59', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_accommodation_food_services'],
    'industry_pct_agriculture_fishing' => ['cell' => 'B60', 'type' => 'chg_pct'],
    'industry_abs_agriculture_fishing' => ['cell' => 'C60', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_agriculture_fishing'],
    'industry_pct_construction' => ['cell' => 'B61', 'type' => 'chg_pct'],
    'industry_abs_construction' => ['cell' => 'C61', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_construction'],
    'industry_pct_educational_services' => ['cell' => 'B62', 'type' => 'chg_pct'],
    'industry_abs_educational_services' => ['cell' => 'C62', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_educational_services'],
    'industry_pct_finance_insurance_real_estate' => ['cell' => 'B63', 'type' => 'chg_pct'],
    'industry_abs_finance_insurance_real_estate' => ['cell' => 'C63', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_finance_insurance_real_estate'],
    'industry_pct_health_care_social_assistance' => ['cell' => 'B64', 'type' => 'chg_pct'],
    'industry_abs_health_care_social_assistance' => ['cell' => 'C64', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_health_care_social_assistance'],
    'industry_pct_manufacturing' => ['cell' => 'B65', 'type' => 'chg_pct'],
    'industry_abs_manufacturing' => ['cell' => 'C65', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_manufacturing'],
    'industry_pct_other_primary' => ['cell' => 'B66', 'type' => 'chg_pct'],
    'industry_abs_other_primary' => ['cell' => 'C66', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_other_primary'],
    'industry_pct_other_private_services' => ['cell' => 'B67', 'type' => 'chg_pct'],
    'industry_abs_other_private_services' => ['cell' => 'C67', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_other_private_services'],
    'industry_pct_professional_scientific_technical_services' => ['cell' => 'B68', 'type' => 'chg_pct'],
    'industry_abs_professional_scientific_technical_services' => ['cell' => 'C68', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_professional_scientific_technical_services'],
    'industry_pct_public_administration' => ['cell' => 'B69', 'type' => 'chg_pct'],
    'industry_abs_public_administration' => ['cell' => 'C69', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_public_administration'],
    'industry_pct_transportation_warehousing' => ['cell' => 'B70', 'type' => 'chg_pct'],
    'industry_abs_transportation_warehousing' => ['cell' => 'C70', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_transportation_warehousing'],
    'industry_pct_utilities' => ['cell' => 'B71', 'type' => 'chg_pct'],
    'industry_abs_utilities' => ['cell' => 'C71', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_utilities'],
    'industry_pct_wholesale_retail_trade' => ['cell' => 'B72', 'type' => 'chg_pct'],
    'industry_abs_wholesale_retail_trade' => ['cell' => 'C72', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_wholesale_retail_trade'],
    'industry_pct_business_building_other_support_services' => ['cell' => 'B73', 'type' => 'chg_pct'],
    'industry_abs_business_building_other_support_services' => ['cell' => 'C73', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_business_building_other_support_services'],
    'industry_pct_information_culture_recreation' => ['cell' => 'B74', 'type' => 'chg_pct'],
    'industry_abs_information_culture_recreation' => ['cell' => 'C74', 'type' => 'chg_abs', 'same_sign' => 'industry_pct_information_culture_recreation']
  ];
  public $descriptions = [
    'abs' => 'Absolute value, positive, no decimals.',
    'pct' => 'Percentage value (0-100), positive, single decimal place.',
    'chg_abs' => 'Change absolute value (+/-), no decimals.',
    'chg_pct' => 'Change percentage (+/-) (0-50), single decimal place.',
    'date_year' => 'Sheet year corresponds to selected year.',
    'date_month' => 'Sheet month corresponds to selected month.',
    'same_sign' => 'Both values agree in numeric sign (+/-).',
    'blank' => 'A blank cell value will be shown as "Not available".',
    'sum' => 'The sum of the cell values matches the total value.',
    'previous_month' => 'The value matches the previous month\'s value.',
  ];

  /**
   * {@inheritdoc}
   */
	public function getFormId()
  {
		return 'ssot_lmmu_form';
	}

  /**
   * {@inheritdoc}
   */
  public function getDescription()
  {
    foreach ($this->monthly_labour_market_updates as $key => $value) {
      $validation = $this->validations[$key];
      if (!empty($validation['type']) && !empty($validation['cell'])) {
        if (is_null($value)) {
          \Drupal::messenger()->addMessage($this->t('❗Cell @cell (<strong>@key</strong> is <strong>blank</strong>) has a warning: <em>@explanation</em>', [
            '@cell' => $validation['cell'],
            '@key' => $key,
            '@explanation' => $this->t($this->descriptions['blank'])
          ]));
        }
        else {
          \Drupal::messenger()->addMessage($this->t('✅ Cell @cell (<strong>@key = @value</strong>) conforms to: <em>@explanation</em>', [
            '@cell' => $validation['cell'],
            '@key' => $key,
            '@value' => $value,
            '@explanation' => $this->t($this->descriptions[$validation['type']])
          ]));
        }
      }
      if (!empty($validation['same_sign']) && !empty($validation['cell'])) {
        \Drupal::messenger()->addMessage($this->t('✅ Cells @cell1 (<strong>@key1 = @value1</strong>) and @cell2 (<strong>@key2 = @value2</strong>) conform to: <em>@explanation</em>', [
          '@cell2' => $validation['cell'],
          '@key2' => $key,
          '@value2' => $value ?? 'N/A',
          '@cell1' => $this->validations[$validation['same_sign']]['cell'],
          '@key1' => $validation['same_sign'],
          '@value1' => $this->monthly_labour_market_updates[$validation['same_sign']] ?? 'N/A',
          '@explanation' => $this->t($this->descriptions['same_sign'])
        ]));
      }
      if (!empty($validation['sum']) && !empty($validation['cell'])) {
        foreach ($validation['sum'] as $sum_keys) {
          $sum = array_sum(array_map(function($sum_key) {
            return $this->monthly_labour_market_updates[$sum_key] ?? 0;
          }, $sum_keys));
          \Drupal::messenger()->addMessage($this->t('✅ Cell @cell (<strong>@key = @value</strong>) and cells @cells (<strong>sum = @sum</strong>) conform to: <em>@explanation</em>', [
            '@cell' => $validation['cell'],
            '@key' => $key,
            '@value' => $value ?? 'N/A',
            '@cells' => implode(' + ', array_map(function($sum_key) {
              return $this->validations[$sum_key]['cell'];
            }, $sum_keys)),
            '@sum' => $sum,
            '@explanation' => $this->t($this->descriptions['sum']),
          ]));
        }
      }
      if (!empty($validation['previous_month']) && !empty($validation['cell'])) {
        \Drupal::messenger()->addMessage($this->t('✅ Cell @cell (<strong>@key = @value</strong>) and previous month cell @cell_previous (<strong>@key_previous</strong>) conform to: <em>@explanation</em>', [
          '@cell' => $validation['cell'],
          '@key' => $key,
          '@value' => $value ?? 'N/A',
          '@cell_previous' => $this->validations[$validation['previous_month']]['cell'],
          '@key_previous' => $validation['previous_month'],
          '@explanation' => $this->t($this->descriptions['previous_month']),
        ]));
      }
    }
    return $this->t('This action will update the SSOT Labour Market Monthly Update for <strong>@month @year</strong>.', [
      '@month' => DateHelper::monthNames(true)[$this->monthly_labour_market_updates['month']],
      '@year' => $this->monthly_labour_market_updates['year']
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
    return new Url('workbc_custom.ssot_lmmu');
  }

  /**
   * {@inheritdoc}
   */
	public function buildForm(array $form, FormStateInterface $form_state)
  {
    // Confirmation step.
    if ($form_state->get('confirmation')) {
      $this->monthly_labour_market_updates = $form_state->get('monthly_labour_market_updates');
      return parent::buildForm($form, $form_state);
    }

    $form['year'] = [
      '#type' => 'number',
      '#title' => $this->t('Year'),
      '#required' => true,
      '#default_value' => idate('Y'),
      '#max' => idate('Y'),
      '#min' => idate('Y') - 5
    ];

    $form['month'] = [
      '#type' => 'select',
      '#title' => $this->t('Month'),
      '#required' => true,
      '#default_value' => idate('m'),
      '#options' => DateHelper::monthNames(true)
    ];

    $form['#attributes']['enctype'] = 'multipart/form-data';
    $form['lmmu'] = array(
      '#type' => 'managed_file',
      '#name' => 'lmmu',
      '#title' => $this->t('LMMU Spreadsheet'),
      '#required' => true,
      '#description' => $this->t('Please upload your Labour Market Monthly Update spreadsheet (Excel .xlsx format).'),
      '#upload_validators' => ['file_validate_extensions' => ['xlsx']],
      '#upload_location' => 'private://ssot/',
    );

    $form['submit_upload'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Submit')
    );

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
    $file = File::load(reset($form_state->getValue('lmmu')));
    if (empty($file)) {
      return;
    }

    $path = \Drupal::service('file_system')->realpath($file->getFileUri());
    $name = basename($path);
    try {
      $spreadsheet = IOFactory::load($path);
    }
    catch (\Exception $e) {
      \Drupal::logger('workbc_ssot')->error('Error validating @name: @error', [
        '@name' => $name, '@error' => $e->getMessage()
      ]);
      $form_state->setErrorByName('lmmu', $this->t('❌ This spreadsheet file is likely invalid. Please refer to the logs for more information.'));
      return;
    }

    // Look for tab called "Sheet3".
    $sheet = array_filter($spreadsheet->getAllSheets(), function($sheet) {
      return strtolower($sheet->getTitle()) == 'sheet3';
    });
    if (empty($sheet)) {
      $form_state->setErrorByName('lmmu', $this->t('❌ Tab "Sheet3" is not found. Please ensure that the tab containing LMMU information is called "Sheet3".'));
      return;
    }
    else {
      $sheet = reset($sheet);
    }

    // Get the previous month dataset to compare some cells.
    $date = ExcelDate::excelToDateTimeObject($sheet->getCell('A3')->getValue());
    $previous_year = $date->format('Y') + 0;
    $previous_month = $date->format('n') - 1;
    if ($previous_month == 0) {
      $previous_month = 12;
      $previous_year -= 1;
    }
    $previous_month = json_decode($this->ssot("monthly_labour_market_updates?year=eq.$previous_year&month=eq.$previous_month")->getBody(), true);
    if (!empty($previous_month)) {
      $previous_month = reset($previous_month);
    }

    // Validate and fill the monthly_labour_market_updates values.
    // @see https://github.com/bcgov/workbc-ssot/blob/master/migration/load/updates/monthly_labour_market_updates.load
    $monthly_labour_market_updates = [];
    $errors = [];
    foreach ($this->validations as $key => $validation) {
      // Set the value. An explicit value overrides the cell value.
      if (array_key_exists('value', $validation)) {
        if (is_array($validation['value'])) {
          $value = $form_state->getValue($validation['value']['form_state_key']);
        }
        else {
          $value = $validation['value'];
        }
      }
      else if (array_key_exists('cell', $validation)) {
        $value = $sheet->getCell($validation['cell'])->getValue();
      }

      // Perform validations based on type.
      if (array_key_exists('type', $validation)) {
        switch ($validation['type']) {
          case 'date_year':
            $date = ExcelDate::excelToDateTimeObject($sheet->getCell($validation['cell'])->getValue());
            if ($date->format('Y') != $value) {
              array_key_push($errors, $key, $this->t('❌ Cell @cell (<strong>@key = @value</strong>) does not conform to: <em>@explanation</em> @suggestion', [
                '@cell' => $validation['cell'],
                '@key' => $key,
                '@value' => $date->format('Y'),
                '@explanation' => $this->t($this->descriptions[$validation['type']]),
                '@suggestion' => $this->t('Please verify that you are uploading the right sheet and/or correct the selection.'),
              ]));
            }
            break;
          case 'date_month':
            $date = ExcelDate::excelToDateTimeObject($sheet->getCell($validation['cell'])->getValue());
            if ($date->format('n') != $value) {
              array_key_push($errors, $key, $this->t('❌ Cell @cell (<strong>@key = @value</strong>) does not conform to: <em>@explanation</em> @suggestion', [
                '@cell' => $validation['cell'],
                '@key' => $key,
                '@value' => $date->format('F'),
                '@explanation' => $this->t($this->descriptions[$validation['type']]),
                '@suggestion' => $this->t('Please verify that you are uploading the right sheet and/or correct the selection.'),
              ]));
            }
            break;
          case 'abs':
            if (!is_numeric($value)) {
              $value = NULL;
            }
            else {
              $value = floatval($value);
              if ($value < 0 || !is_number_integer($value)) {
                array_key_push($errors, $key, $this->t('❌ Cell @cell (<strong>@key = @value</strong>) does not conform to: <em>@explanation</em> @suggestion', [
                  '@cell' => $validation['cell'],
                  '@key' => $key,
                  '@value' => $value,
                  '@explanation' => $this->t($this->descriptions[$validation['type']]),
                  '@suggestion' => $this->t('Please correct the value.'),
                ]));
              }
            }
            break;
          case 'pct':
            if (!is_numeric($value)) {
              $value = NULL;
            }
            else {
              $value = floatval($value);
              if ($value < 0 || number_precision($value) > 1 || $value > 100) {
                array_key_push($errors, $key, $this->t('❌ Cell @cell (<strong>@key = @value</strong>) does not conform to: <em>@explanation</em> @suggestion', [
                  '@cell' => $validation['cell'],
                  '@key' => $key,
                  '@value' => $value,
                  '@explanation' => $this->t($this->descriptions[$validation['type']]),
                  '@suggestion' => $this->t('Please correct the value.'),
                ]));
              }
            }
            break;
          case 'chg_pct':
            if (!is_numeric($value)) {
              $value = NULL;
            }
            else {
              $value = floatval($value);
              if (number_precision($value) > 1 || abs($value) > 50) {
                array_key_push($errors, $key, $this->t('❌ Cell @cell (<strong>@key = @value</strong>) does not conform to: <em>@explanation</em> @suggestion', [
                  '@cell' => $validation['cell'],
                  '@key' => $key,
                  '@value' => $value,
                  '@explanation' => $this->t($this->descriptions[$validation['type']]),
                  '@suggestion' => $this->t('Please correct the value.'),
                ]));
              }
            }
            break;
          case 'chg_abs':
            if (!is_numeric($value)) {
              $value = NULL;
            }
            else {
              $value = floatval($value);
              if (!is_number_integer($value)) {
                array_key_push($errors, $key, $this->t('❌ Cell @cell (<strong>@key = @value</strong>) does not conform to: <em>@explanation</em> @suggestion', [
                  '@cell' => $validation['cell'],
                  '@key' => $key,
                  '@value' => $value,
                  '@explanation' => $this->t($this->descriptions[$validation['type']]),
                  '@suggestion' => $this->t('Please correct the value.'),
                ]));
              }
            }
            break;
          }
      }

      // Perform inter-cell same sign validation.
      if (array_key_exists('same_sign', $validation)) {
        $related_value = $monthly_labour_market_updates[$validation['same_sign']];
        if (!(
          (is_null($related_value) && is_null($value)) ||
          ($related_value * $value >= 0)
        )) {
          array_key_push($errors, $key, $this->t('❌ Cells @cell1 (<strong>@key1 = @value1</strong>) and @cell2 (<strong>@key2 = @value2</strong>) do not conform to: <em>@explanation</em> @suggestion', [
            '@cell1' => $this->validations[$validation['same_sign']]['cell'],
            '@key1' => $validation['same_sign'],
            '@value1' => $monthly_labour_market_updates[$validation['same_sign']],
            '@cell2' => $validation['cell'],
            '@key2' => $key,
            '@value2' => $value ?? 'N/A',
            '@explanation' => $this->t($this->descriptions['same_sign']),
            '@suggestion' => $this->t('Please correct the values.'),
          ]));
        }
      }

      // Perform inter-cell sum validation.
      if (array_key_exists('sum', $validation)) {
        foreach ($validation['sum'] as $sum_keys) {
          $sum = array_sum(array_map(function($sum_key) use ($monthly_labour_market_updates) {
            return $monthly_labour_market_updates[$sum_key] ?? 0;
          }, $sum_keys));
          if (abs($sum - ($value ?? 0)) > 100) {
            array_key_push($errors, $key, $this->t('❌ Cell @cell (<strong>@key = @value</strong>) and cells @cells (<strong>sum = @sum</strong>) do not conform to: <em>@explanation</em> @suggestion', [
              '@cell' => $validation['cell'],
              '@key' => $key,
              '@value' => $value ?? 'N/A',
              '@cells' => implode(' + ', array_map(function($sum_key) {
                return $this->validations[$sum_key]['cell'];
              }, $sum_keys)),
              '@sum' => $sum,
              '@explanation' => $this->t($this->descriptions['sum']),
              '@suggestion' => $this->t('Please correct the values.'),
            ]));
          }
        }
      }

      // Perform inter-sheet previous month validation.
      if (!empty($previous_month) && array_key_exists('previous_month', $validation)) {
        $previous_value = $previous_month[$validation['previous_month']];
        if (!(
          (is_null($previous_value) && is_null($value)) ||
          ($previous_value == $value)
        )) {
          array_key_push($errors, $key, $this->t('❌ Cell @cell (<strong>@key = @value</strong>) and previous month cell @cell_previous (<strong>@key_previous = @value_previous</strong>) do not conform to: <em>@explanation</em> @suggestion', [
            '@cell' => $validation['cell'],
            '@key' => $key,
            '@value' => $value ?? 'N/A',
            '@cell_previous' => $this->validations[$validation['previous_month']]['cell'],
            '@key_previous' => $validation['previous_month'],
            '@value_previous' => $previous_value ?? 'N/A',
            '@explanation' => $this->t($this->descriptions['previous_month']),
            '@suggestion' => $this->t('Please correct the value.'),
          ]));
        }
      }

      // Set the value.
      if (empty($validation['ignore'])) {
        $monthly_labour_market_updates[$key] = $value;
      }
    }

    // Display errors if any.
    if (!empty($errors)) {
      $all_errors = array_merge(...array_values($errors));
      foreach ($all_errors as $error) {
        \Drupal::messenger()->addError($error);
      }
      $form_state->setErrorByName('lmms', $this->t('Please re-upload the sheet once the errors above have been corrected.'));
      return;
    }

    // Good to go: Remember the value for submission.
    $form_state->set('monthly_labour_market_updates', $monthly_labour_market_updates);
    $form_state->set('file_id', $file->id());
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

    $this->monthly_labour_market_updates = $form_state->get('monthly_labour_market_updates');
    $year = $this->monthly_labour_market_updates['year'];
    $month = $this->monthly_labour_market_updates['month'];
    $check = json_decode($this->ssot("monthly_labour_market_updates?year=eq.$year&month=eq.$month")->getBody(), true);
    if (empty($check)) {
      $result = $this->ssot('monthly_labour_market_updates', NULL, 'POST', json_encode($this->monthly_labour_market_updates));
    }
    else {
      $result = $this->ssot("monthly_labour_market_updates?year=eq.$year&month=eq.$month", NULL, 'PATCH', json_encode($this->monthly_labour_market_updates));
    }
    if ($result && $result->getStatusCode() < 300) {
      \Drupal::messenger()->addMessage(t('Labour Market Monthly Update successfully updated for <strong>@month @year</strong>. <a href="@url">Click here</a> to see it!', [
        '@year' => $this->monthly_labour_market_updates['year'],
        '@month' => DateHelper::monthNames(true)[$this->monthly_labour_market_updates['month']],
        '@url' => Url::fromUri('internal:/research-labour-market/bcs-economy/labour-market-monthly-update')->toString()
      ]));
      $file = \Drupal\file\Entity\File::load($form_state->get('file_id'));
      $file->setPermanent();
      $file->save();
      \Drupal::logger('workbc_ssot')->info(t('Labour Market Monthly Update successfully updated for <strong>@month @year</strong> with file <a href="@uri">@filename</a>.', [
        '@year' => $this->monthly_labour_market_updates['year'],
        '@month' => DateHelper::monthNames(true)[$this->monthly_labour_market_updates['month']],
        '@uri' => \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()),
        '@filename' => $file->getFilename(),
      ]));
    }
    else {
      \Drupal::messenger()->addError(t('❌ An error occurred while updating Labour Market Monthly Update. Please refer to the logs for more information.'));
      if ($result) {
        \Drupal::logger('workbc_ssot')->error(json_decode($result->getBody(), true));
      }
    }
  }

  function ssot($url, $read_timeout = NULL, $method = 'GET', $body = null)
  {
    $ssot = rtrim(\Drupal::config('workbc')->get('ssot_url'), '/');
    $client = new Client();
    try {
      $options = [];
      if ($read_timeout) {
        $options['read_timeout'] = $read_timeout;
      }
      switch (strtolower($method)) {
        case 'get':
          $response = $client->get($ssot . '/' . $url, $options);
          break;
        case 'post':
        case 'patch':
          $options['body'] = $body;
          $response = $client->request($method, $ssot . '/' . $url, $options);
          break;
      }
      return $response;
    }
    catch (RequestException $e) {
      \Drupal::logger('workbc_ssot')->error($e->getMessage());
      return NULL;
    }
  }
}
