<?php

namespace Drupal\workbc_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet;

/**
* Class SsotUploadLmmuForm.
*
* @package Drupal\workbc_custom\Form
*/
class SsotUploadLmmuForm extends FormBase {
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
      '#options' => [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December'
      ]
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
    $path = \Drupal::service('file_system')->realpath($file->getFileUri());
    $name = basename($path);
    try {
      $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    }
    catch (\Exception $e) {
      \Drupal::logger('workbc')->error('Error validating @name: @error', [
        '@name' => $name, '@error' => $e->getMessage()
      ]);
      $form_state->setErrorByName('lmmu', "This spreadsheet file is likely invalid. Please refer to the logs for more information.");
      return;
    }

    // Look for tab called "Sheet3".
    $sheet = array_filter($spreadsheet->getAllSheets(), function($sheet) {
      return strtolower($sheet->getTitle()) == 'sheet3';
    });
    if (empty($sheet)) {
      $form_state->setErrorByName('lmmu', "Tab \"Sheet3\" is not found. Please ensure that the tab containing LMMU information is called \"Sheet3\".");
    }
    else {
      $sheet = reset($sheet);
    }

    // Validate and fill the monthly_labour_market_updates values.
    // @see https://github.com/bcgov/workbc-ssot/blob/master/migration/load/updates/monthly_labour_market_updates.load
    $monthly_labour_market_updates = [
      'year' => ['value' => $form_state->getValue('year')],
      'month' => ['value' => $form_state->getValue('month')],

      'total_employed' => ['cell' => 'B3'],
      'total_unemployed' => ['cell' => 'B37'],
      'total_unemployed_previous' => ['cell' => 'A37'],

      'employment_by_age_group_15_24' => ['cell' => 'C8'],
      'employment_by_age_group_25_54' => ['cell' => 'C9'],
      'employment_by_age_group_55' => ['cell' => 'C10'],
      'employment_by_age_group_15_24_previous' => ['cell' => 'B8'],
      'employment_by_age_group_25_54_previous' => ['cell' => 'B9'],
      'employment_by_age_group_55_previous' => ['cell' => 'B10'],

      'employment_by_gender_women' => ['cell' => 'C12'],
      'employment_by_gender_men' => ['cell' => 'C13'],
      'employment_by_gender_women_previous' => ['cell' => 'B12'],
      'employment_by_gender_men_previous' => ['cell' => 'B13'],

      'employment_change_pct_total_employment' => ['cell' => 'B18'],
      'employment_change_abs_total_employment' => ['cell' => 'C18'],
      'employment_change_pct_full_time_jobs' => ['cell' => 'B19'],
      'employment_change_abs_full_time_jobs' => ['cell' => 'C19'],
      'employment_change_pct_part_time_jobs' => ['cell' => 'B20'],
      'employment_change_abs_part_time_jobs' => ['cell' => 'C20'],

      'employment_rate_change_pct_unemployment' => ['cell' => 'B22'],
      'employment_rate_pct_unemployment' => ['cell' => 'C22'],
      'employment_rate_change_pct_participation' => ['cell' => 'B23'],
      'employment_rate_pct_participation' => ['cell' => 'C23'],
      'employment_rate_change_pct_unemployment_previous' => ['cell' => 'E22'],
      'employment_rate_pct_unemployment_previous' => ['cell' => 'F22'],
      'employment_rate_change_pct_participation_previous' => ['cell' => 'E23'],
      'employment_rate_pct_participation_previous' => ['cell' => 'F23'],

      'population_british_columbia' => ['cell' => 'B26'],
      'population_vancouver_island_coast' => ['cell' => 'B27'],
      'population_mainland_southwest' => ['cell' => 'B28'],
      'population_thompson_okanagan' => ['cell' => 'B29'],
      'population_kootenay' => ['cell' => 'B30'],
      'population_cariboo' => ['cell' => 'B31'],
      'population_north_coast_nechako' => ['cell' => 'B32'],
      'population_northeast' => ['cell' => 'B33'],

      'unemployment_pct_british_columbia' => ['cell' => 'B41'],
      'unemployment_pct_british_columbia_previous' => ['cell' => 'E41'],
      'total_jobs_british_columbia' => ['cell' => 'C41'],
      'unemployment_pct_vancouver_island_coast' => ['cell' => 'B42'],
      'unemployment_pct_vancouver_island_coast_previous' => ['cell' => 'E42'],
      'total_jobs_vancouver_island_coast' => ['cell' => 'C42'],
      'unemployment_pct_mainland_southwest' => ['cell' => 'B43'],
      'unemployment_pct_mainland_southwest_previous' => ['cell' => 'E43'],
      'total_jobs_mainland_southwest' => ['cell' => 'C43'],
      'unemployment_pct_thompson_okanagan' => ['cell' => 'B44'],
      'unemployment_pct_thompson_okanagan_previous' => ['cell' => 'E44'],
      'total_jobs_thompson_okanagan' => ['cell' => 'C44'],
      'unemployment_pct_kootenay' => ['cell' => 'B45'],
      'unemployment_pct_kootenay_previous' => ['cell' => 'E45'],
      'total_jobs_kootenay' => ['cell' => 'C45'],
      'unemployment_pct_cariboo' => ['cell' => 'B46'],
      'unemployment_pct_cariboo_previous' => ['cell' => 'E46'],
      'total_jobs_cariboo' => ['cell' => 'C46'],
      'unemployment_pct_north_coast_nechako' => ['cell' => 'B47'],
      'unemployment_pct_north_coast_nechako_previous' => ['cell' => 'E47'],
      'total_jobs_north_coast_nechako' => ['cell' => 'C47'],
      'unemployment_pct_northeast' => ['cell' => 'B48'],
      'unemployment_pct_northeast_previous' => ['cell' => 'E48'],
      'total_jobs_northeast' => ['cell' => 'C48'],

      'city_unemployment_pct_kelowna' => ['value' => NULL],
      'city_unemployment_pct_abbotsford_mission' => ['value' => NULL],
      'city_unemployment_pct_vancouver' => ['value' => NULL],
      'city_unemployment_pct_victoria' => ['value' => NULL],

      'industry_pct_accommodation_and_food_services' => ['cell' => 'B59'],
      'industry_abs_accommodation_and_food_services' => ['cell' => 'C59'],
      'industry_pct_agriculture' => ['cell' => 'B60'],
      'industry_abs_agriculture' => ['cell' => 'C60'],
      'industry_pct_construction' => ['cell' => 'B61'],
      'industry_abs_construction' => ['cell' => 'C61'],
      'industry_pct_educational_services' => ['cell' => 'B62'],
      'industry_abs_educational_services' => ['cell' => 'C62'],
      'industry_pct_finance_insurance_real_estate_rental' => ['cell' => 'B63'],
      'industry_abs_finance_insurance_real_estate_rental' => ['cell' => 'C63'],
      'industry_pct_health_care_and_social_assistance' => ['cell' => 'B64'],
      'industry_abs_health_care_and_social_assistance' => ['cell' => 'C64'],
      'industry_pct_manufacturing' => ['cell' => 'B65'],
      'industry_abs_manufacturing' => ['cell' => 'C65'],
      'industry_pct_other_primary' => ['cell' => 'B66'],
      'industry_abs_other_primary' => ['cell' => 'C66'],
      'industry_pct_other_services' => ['cell' => 'B67'],
      'industry_abs_other_services' => ['cell' => 'C67'],
      'industry_pct_professional_scientific_and_technical' => ['cell' => 'B68'],
      'industry_abs_professional_scientific_and_technical' => ['cell' => 'C68'],
      'industry_pct_public_administration' => ['cell' => 'B69'],
      'industry_abs_public_administration' => ['cell' => 'C69'],
      'industry_pct_transportation_and_warehousing' => ['cell' => 'B70'],
      'industry_abs_transportation_and_warehousing' => ['cell' => 'C70'],
      'industry_pct_utilities' => ['cell' => 'B71'],
      'industry_abs_utilities' => ['cell' => 'C71'],
      'industry_pct_wholesale_and_retail_trade' => ['cell' => 'B72'],
      'industry_abs_wholesale_and_retail_trade' => ['cell' => 'C72'],
      'industry_pct_business_building_other_support_services' => ['cell' => 'B73'],
      'industry_abs_business_building_other_support_services' => ['cell' => 'C73'],
      'industry_pct_information_culture_recreation' => ['cell' => 'B74'],
      'industry_abs_information_culture_recreation' => ['cell' => 'C74']
    ];
    foreach ($monthly_labour_market_updates as $key => $action) {
      if (array_key_exists('value', $action)) {
        $monthly_labour_market_updates[$key] = $action['value'];
      }
      else if (array_key_exists('cell', $action)) {
        $monthly_labour_market_updates[$key] = $sheet->getCell($action['cell'])->getValue();
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
  }
}
