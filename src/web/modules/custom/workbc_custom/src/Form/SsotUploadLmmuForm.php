<?php

namespace Drupal\workbc_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use \Drupal\Core\Datetime\DateHelper;
use \Drupal\Core\Url;

// @see https://stackoverflow.com/a/2430144/209184
function number_precision($value) {
  return strlen(substr(strrchr($value, "."), 1));
}

function is_number_integer($value) {
  return abs($value - round($value)) <= PHP_FLOAT_EPSILON;
}

function array_push_key(&$array, $key, $value) {
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
class SsotUploadLmmuForm extends FormBase {
  private $monthly_labour_market_updates = NULL;

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
	public function buildForm(array $form, FormStateInterface $form_state)
  {
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
      '#title' => t('LMMU Spreadsheet'),
      '#required' => true,
      '#description' => t('Please upload your Labour Market Monthly Update spreadsheet (Excel .xlsx   format).'),
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
    $file = File::load(reset($form_state->getValue('lmmu')));
    if (empty($file)) return;

    $path = \Drupal::service('file_system')->realpath($file->getFileUri());
    $name = basename($path);
    try {
      $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    }
    catch (\Exception $e) {
      \Drupal::logger('workbc')->error('Error validating @name: @error', [
        '@name' => $name, '@error' => $e->getMessage()
      ]);
      $form_state->setErrorByName('lmmu', "❌ This spreadsheet file is likely invalid. Please refer to the logs for more information.");
      return;
    }

    // Look for tab called "Sheet3".
    $sheet = array_filter($spreadsheet->getAllSheets(), function($sheet) {
      return strtolower($sheet->getTitle()) == 'sheet3';
    });
    if (empty($sheet)) {
      $form_state->setErrorByName('lmmu', "❌ Tab \"Sheet3\" is not found. Please ensure that the tab containing LMMU information is called \"Sheet3\".");
      return;
    }
    else {
      $sheet = reset($sheet);
    }

    // Validate and fill the monthly_labour_market_updates values.
    // @see https://github.com/bcgov/workbc-ssot/blob/master/migration/load/updates/monthly_labour_market_updates.load
    $monthly_labour_market_updates = [];
    $errors = [];
    $validations = [
      'year' => ['value' => intval($form_state->getValue('year')), 'cell' => 'A3', 'type' => 'date_year'],
      'month' => ['value' => intval($form_state->getValue('month')), 'cell' => 'A3', 'type' => 'date_month'],

      'total_employed' => ['cell' => 'B3', 'type' => 'abs'],
      'total_unemployed' => ['cell' => 'B37', 'type' => 'abs'],
      'total_unemployed_previous' => ['cell' => 'A37', 'type' => 'abs'],

      'employment_by_age_group_15_24' => ['cell' => 'C8', 'type' => 'abs'],
      'employment_by_age_group_25_54' => ['cell' => 'C9', 'type' => 'abs'],
      'employment_by_age_group_55' => ['cell' => 'C10', 'type' => 'abs'],
      'employment_by_age_group_15_24_previous' => ['cell' => 'B8', 'type' => 'abs'],
      'employment_by_age_group_25_54_previous' => ['cell' => 'B9', 'type' => 'abs'],
      'employment_by_age_group_55_previous' => ['cell' => 'B10', 'type' => 'abs'],

      'employment_by_gender_women' => ['cell' => 'C12', 'type' => 'abs'],
      'employment_by_gender_men' => ['cell' => 'C13', 'type' => 'abs'],
      'employment_by_gender_women_previous' => ['cell' => 'B12', 'type' => 'abs'],
      'employment_by_gender_men_previous' => ['cell' => 'B13', 'type' => 'abs'],

      'employment_change_pct_total_employment' => ['cell' => 'B18', 'type' => 'chg_pct'],
      'employment_change_abs_total_employment' => ['cell' => 'C18', 'type' => 'chg_abs', 'related' => 'employment_change_pct_total_employment'],
      'employment_change_pct_full_time_jobs' => ['cell' => 'B19', 'type' => 'chg_pct'],
      'employment_change_abs_full_time_jobs' => ['cell' => 'C19', 'type' => 'chg_abs', 'related' => 'employment_change_pct_full_time_jobs'],
      'employment_change_pct_part_time_jobs' => ['cell' => 'B20', 'type' => 'chg_pct'],
      'employment_change_abs_part_time_jobs' => ['cell' => 'C20', 'type' => 'chg_abs', 'related' => 'employment_change_pct_part_time_jobs'],

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

      'city_unemployment_pct_kelowna' => ['value' => NULL],
      'city_unemployment_pct_abbotsford_mission' => ['value' => NULL],
      'city_unemployment_pct_vancouver' => ['value' => NULL],
      'city_unemployment_pct_victoria' => ['value' => NULL],

      'industry_pct_accommodation_food_services' => ['cell' => 'B59', 'type' => 'chg_pct'],
      'industry_abs_accommodation_food_services' => ['cell' => 'C59', 'type' => 'chg_abs', 'related' => 'industry_pct_accommodation_food_services'],
      'industry_pct_agriculture_fishing' => ['cell' => 'B60', 'type' => 'chg_pct'],
      'industry_abs_agriculture_fishing' => ['cell' => 'C60', 'type' => 'chg_abs', 'related' => 'industry_pct_agriculture_fishing'],
      'industry_pct_construction' => ['cell' => 'B61', 'type' => 'chg_pct'],
      'industry_abs_construction' => ['cell' => 'C61', 'type' => 'chg_abs', 'related' => 'industry_pct_construction'],
      'industry_pct_educational_services' => ['cell' => 'B62', 'type' => 'chg_pct'],
      'industry_abs_educational_services' => ['cell' => 'C62', 'type' => 'chg_abs', 'related' => 'industry_pct_educational_services'],
      'industry_pct_finance_insurance_real_estate' => ['cell' => 'B63', 'type' => 'chg_pct'],
      'industry_abs_finance_insurance_real_estate' => ['cell' => 'C63', 'type' => 'chg_abs', 'related' => 'industry_pct_finance_insurance_real_estate'],
      'industry_pct_health_care_social_assistance' => ['cell' => 'B64', 'type' => 'chg_pct'],
      'industry_abs_health_care_social_assistance' => ['cell' => 'C64', 'type' => 'chg_abs', 'related' => 'industry_pct_health_care_social_assistance'],
      'industry_pct_manufacturing' => ['cell' => 'B65', 'type' => 'chg_pct'],
      'industry_abs_manufacturing' => ['cell' => 'C65', 'type' => 'chg_abs', 'related' => 'industry_pct_manufacturing'],
      'industry_pct_other_primary' => ['cell' => 'B66', 'type' => 'chg_pct'],
      'industry_abs_other_primary' => ['cell' => 'C66', 'type' => 'chg_abs', 'related' => 'industry_pct_other_primary'],
      'industry_pct_other_private_services' => ['cell' => 'B67', 'type' => 'chg_pct'],
      'industry_abs_other_private_services' => ['cell' => 'C67', 'type' => 'chg_abs', 'related' => 'industry_pct_other_private_services'],
      'industry_pct_professional_scientific_technical_services' => ['cell' => 'B68', 'type' => 'chg_pct'],
      'industry_abs_professional_scientific_technical_services' => ['cell' => 'C68', 'type' => 'chg_abs', 'related' => 'industry_pct_professional_scientific_technical_services'],
      'industry_pct_public_administration' => ['cell' => 'B69', 'type' => 'chg_pct'],
      'industry_abs_public_administration' => ['cell' => 'C69', 'type' => 'chg_abs', 'related' => 'industry_pct_public_administration'],
      'industry_pct_transportation_warehousing' => ['cell' => 'B70', 'type' => 'chg_pct'],
      'industry_abs_transportation_warehousing' => ['cell' => 'C70', 'type' => 'chg_abs', 'related' => 'industry_pct_transportation_warehousing'],
      'industry_pct_utilities' => ['cell' => 'B71', 'type' => 'chg_pct'],
      'industry_abs_utilities' => ['cell' => 'C71', 'type' => 'chg_abs', 'related' => 'industry_pct_utilities'],
      'industry_pct_wholesale_retail_trade' => ['cell' => 'B72', 'type' => 'chg_pct'],
      'industry_abs_wholesale_retail_trade' => ['cell' => 'C72', 'type' => 'chg_abs', 'related' => 'industry_pct_wholesale_retail_trade'],
      'industry_pct_business_building_other_support_services' => ['cell' => 'B73', 'type' => 'chg_pct'],
      'industry_abs_business_building_other_support_services' => ['cell' => 'C73', 'type' => 'chg_abs', 'related' => 'industry_pct_business_building_other_support_services'],
      'industry_pct_information_culture_recreation' => ['cell' => 'B74', 'type' => 'chg_pct'],
      'industry_abs_information_culture_recreation' => ['cell' => 'C74', 'type' => 'chg_abs', 'related' => 'industry_pct_information_culture_recreation']
    ];
    foreach ($validations as $key => $action) {
      // Set the value. An explicit value overrides the cell value.
      if (array_key_exists('value', $action)) {
        $value = $action['value'];
      }
      else if (array_key_exists('cell', $action)) {
        $value = $sheet->getCell($action['cell'])->getValue();
      }

      // Perform validations.
      if (array_key_exists('type', $action)) {
        switch ($action['type']) {
          case 'date_year':
            $date = ExcelDate::excelToDateTimeObject($sheet->getCell($action['cell'])->getValue());
            if ($date->format('Y') != $value) {
              array_push_key($errors, $key, "Selected year $value is expected to correspond to the date in cell {$action['cell']}, but found {$date->format('Y')} instead. Please verify that you are uploading the right sheet and/or correct the selection.");
            }
            break;
          case 'date_month':
            $date = ExcelDate::excelToDateTimeObject($sheet->getCell($action['cell'])->getValue());
            $monthName = DateHelper::monthNames(true)[$value];
            if ($date->format('n') != $value) {
              array_push_key($errors, $key, "Selected month $monthName is expected to correspond to the date in cell {$action['cell']}, but found {$date->format('F')} instead. Please verify that you are uploading the right sheet and/or correct the selection.");
            }
            break;
          case 'abs':
            if (!is_numeric($value)) {
              // TODO check for required value.
              $value = NULL;
            }
            else {
              $value = floatval($value);
              if ($value < 0 || !is_number_integer($value)) {
                array_push_key($errors, $key, "Cell {$action['cell']} is expected to contain a positive absolute value, but found $value instead. Please correct the value.");
              }
            }
            break;
          case 'pct':
            if (!is_numeric($value)) {
              // TODO check for required value.
              $value = NULL;
            }
            else {
              $value = floatval($value);
              if ($value < 0 || number_precision($value) > 1 || $value > 100) {
                array_push_key($errors, $key, "Cell {$action['cell']} is expected to contain a positive percentage value with a single decimal, but found $value instead. Please correct the value.");
              }
            }
            break;
          case 'chg_pct':
            if (!is_numeric($value)) {
              // TODO check for required value.
              $value = NULL;
            }
            else {
              $value = floatval($value);
              if (number_precision($value) > 1 || $value > 100) {
                array_push_key($errors, $key, "Cell {$action['cell']} is expected to contain a change(+/-) percentage value with a single decimal, but found $value instead. Please correct the value.");
              }
            }
            break;
          case 'chg_abs':
            if (!is_numeric($value)) {
              // TODO check for required value.
              $value = NULL;
            }
            else {
              $value = floatval($value);
              if (!is_number_integer($value)) {
                array_push_key($errors, $key, "Cell {$action['cell']} is expected to contain a change(+/-) absolute value, but found $value instead. Please correct the value.");
              }
            }
            if (array_key_exists('related', $action)) {
              $related_value = $monthly_labour_market_updates[$action['related']];
              if (!(
                (is_null($related_value) && is_null($value)) ||
                ($related_value * $value >= 0)
              )) {
                $related_cell = $validations[$action['related']]['cell'];
                array_push_key($errors, $key, "Cells {$related_cell} and {$action['cell']} are expected to have the same numeric sign(+/-), but found $related_value and $value instead. Please correct the values.");
              }
            }
            break;
          }
      }
      if (empty($action['ignore'])) {
        $monthly_labour_market_updates[$key] = $value;
      }
    }

    // Display errors if any.
    if (!empty($errors)) {
      $all_errors = array_merge(...array_values($errors));
      foreach ($all_errors as $error) {
        \Drupal::messenger()->addError("❌ $error");
      }
      $form_state->setErrorByName('lmms', 'Please re-upload the sheet once the errors above have been corrected.');
      return;
    }

    // Good to go: Remember the value for submission.
    $this->monthly_labour_market_updates = $monthly_labour_market_updates;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $ssot = \Drupal\Core\Database\Database::getConnection('lmmu','ssot');
    try {
      $check = $ssot->query("SELECT 1 FROM {monthly_labour_market_updates} WHERE year=:year AND month=:month", [
        ':year' => $this->monthly_labour_market_updates['year'],
        ':month' => $this->monthly_labour_market_updates['month'],
      ])->fetchAll();
      if (empty($check)) {
        $result = $ssot->insert('monthly_labour_market_updates')
          ->fields($this->monthly_labour_market_updates)
          ->execute();
      }
      else {
        $result = $ssot->update('monthly_labour_market_updates')
          ->fields($this->monthly_labour_market_updates)
          ->condition('year', $this->monthly_labour_market_updates['year'])
          ->condition('month', $this->monthly_labour_market_updates['month'])
          ->execute();
      }
      \Drupal::messenger()->addMessage(t('Labour Market Monthly Update successfully updated for @month @year. <a href="@url">Click here</a> to see it!', [
        '@year' => $this->monthly_labour_market_updates['year'],
        '@month' =>  DateHelper::monthNames(true)[$this->monthly_labour_market_updates['month']],
        '@url' => Url::fromUri('internal:/research-labour-market/bcs-economy/labour-market-monthly-update')->toString()
      ]));
    }
    catch (\Exception $e) {
      \Drupal::messenger()->addError($e->getMessage());
    }
  }
}
