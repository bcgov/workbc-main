<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarket;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "labourmarket_employment_industries_table",
 *   label = @Translation("Employment Industries Table"),
 *   description = @Translation("An extra field to display industry employment change table."),
 *   bundles = {
 *     "node.labour_market_monthly",
 *   }
 * )
 */
class LabourMarketEmploymentIndustriesTable extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Employment Industries Change Table');
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

    if(empty($entity->ssot_data['monthly_labour_market_updates'])){
      $output = '<div>'.WORKBC_EXTRA_FIELDS_NOT_AVAILABLE.'</div>';
      return [
        ['#markup' => $output ],
      ];
    }

    $data = $entity->ssot_data['monthly_labour_market_updates'][0];
    $header = [$this->t('Industry'), $this->t("Employment (change since last month)"),$this->t("Employment (% change since last month)")];
    $data = $this->getIndustryHighlights($data);

    $rows = [];
    foreach($data as $values){
      $rows[]['data'] = [
        'industry' => [
          'data' => $values['industry'],
          'class' => ['label']
        ],
        'abs' => [
          'data' => $values['abs'],
          'data-label' => $this->t("Employment (change since last month)"),
          'class' => ['data-row', 'jobs-abs'],
          'align' => "right"
        ],
        'per' => [
          'data' => $values['per'],
          'data-label' => $this->t("Employment (% change since last month)"),
          'class' => ['data-row', 'jobs-per'],
          'align' => "right"
        ]
      ];
    }

    $source_text = !empty($entity->ssot_data['sources']['no-datapoint'])?$entity->ssot_data['sources']['no-datapoint']:WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    $output = '<div class="lm-source"><strong>'.$this->t("Source").': </strong>'.$source_text.'</div>';
    return [
      [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#attributes' => array('class'=>array('lm-table-industries')),
        '#header_columns' => 4,
      ],
      [
        '#markup' => $output
      ]

    ];
  }

  public function getIndustryHighlights($values){
    $industries = [];
    $needle = 'industry_abs_';
    $pct_needle = 'industry_pct_';

    $industries_mapping = [
      'accommodation_and_food_services' => 'Accommodation and food services',
      'agriculture' => 'Agriculture',
      'business_building_other_support_services' => 'Business, building and other support services',
      'construction' => 'Construction',
      'educational_services' => 'Educational services',
      'finance_insurance_real_estate_rental' => 'Finance, insurance, real estate, rental and leasing',
      'health_care_and_social_assistance' => 'Health care and social assistance',
      'information_culture_recreation' => 'Information, culture and recreation',
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
          $options = array(
            'decimals' => 0,
            'positive_sign' => TRUE,
            'na_if_empty' => TRUE,
          );
          $industrysubstring = str_replace($needle, "", $key);
          $industries[$industrysubstring]['industry'] = $industries_mapping[$industrysubstring];
          $industries[$industrysubstring]['abs'] = ssotFormatNumber($value, $options);
        }
        //percentage value
        if(strpos($key, $pct_needle) !== false){
          $options = array(
            'decimals' => 1,
            'suffix' => "%",
            'positive_sign' => TRUE,
            'na_if_empty' => TRUE,
          );
          $industrysubstring = str_replace($pct_needle, "", $key);
          $industries[$industrysubstring]['industry'] = $industries_mapping[$industrysubstring];
          $industries[$industrysubstring]['per'] = ssotFormatNumber($value, $options);
        }
      }
    }
    usort($industries, function ($a, $b) {
      return strnatcmp($a['industry'], $b['industry']);
    });
    return $industries;
  }

}
