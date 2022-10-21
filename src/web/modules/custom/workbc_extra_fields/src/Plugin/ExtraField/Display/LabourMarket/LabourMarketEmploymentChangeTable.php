<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarket;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "labourmarket_employment_change_table",
 *   label = @Translation("Employment Change Table"),
 *   description = @Translation("An extra field to display industry employment change table."),
 *   bundles = {
 *     "node.labour_market_monthly",
 *   }
 * )
 */
class LabourMarketEmploymentChangeTable extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Employment Change Table');
  }

  /**
   * {@inheritdoc}
   */
  public function getLabelDisplay() {

    return 'above';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {

    //values
    $year = $entity->ssot_data['monthly_labour_market_updates'][0]['year'];
    //month
    $monthNum = $entity->ssot_data['monthly_labour_market_updates'][0]['month'];
    $month = date ('F', mktime(0, 0, 0, $monthNum, 10));

    $header = [$this->t('Industry'), $this->t("Employment (change since last month)"),$this->t("Employment (% change since last month)")];
    $data = $this->getIndustryHighlights($entity->ssot_data['monthly_labour_market_updates'][0]);

    $rows = [];
    foreach($data as $values){
      $rows[] = [$values['industry'], $values['abs'], $values['per']];
    }
    
    //TODO: Previous year values & image

    return [
      [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#attributes' => array('class'=>array('my-table')),
        '#header_columns' => 4,
      ],
    ];
  }

  public function getIndustryHighlights($values){
    $industries = [];
    $needle = 'industry_abs_';
    $pct_needle = 'industry_pct_';

    $industries_mapping = [
      'accommodation_and_food_services' => 'Accommodation and food services',
      'agriculture' => 'Agriculture',
      'construction' => 'Construction',
      'educational_services' => 'Educational services',
      'finance_insurance_real_estate_rental' => 'Finance, insurance, real estate, rental and leasing',
      'health_care_and_social_assistance' => 'Health care and social assistance',
      'manufacturing' => 'Manufacturing',
      'other_primary' => 'Other Primary',
      'other_services' => 'Other services (except public administration)',
      'professional_scientific_and_technical' =>'Professional, scientific and technical services',
      'public_administration' =>'Public administration',
      'transportation_and_warehousing' =>'Transportation and warehousing',
      'utilities' => 'Utilities',
      'wholesale_and_retail_trade' => 'Wholesale and retail trade'
    ];

    //inform decimal format round off done.
    if(!empty($values)){
      foreach($values as $key => $value){
        //absolute value
        if(strpos($key, $needle) !== false){
          $industrysubstring = str_replace($needle, "", $key);
          $industries[$industrysubstring]['industry'] = $industries_mapping[$industrysubstring];
          $industries[$industrysubstring]['abs'] = number_format($value);
        }
        //percentage value
        if(strpos($key, $pct_needle) !== false){
          $industrysubstring = str_replace($pct_needle, "", $key);
          $industries[$industrysubstring]['industry'] = $industries_mapping[$industrysubstring];
          $industries[$industrysubstring]['per'] = number_format($value, 2, '.', '').'%';
        }
      }
    }
    // print_r($industries); exit;
    return $industries;
  }

}
