<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarket;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "labourmarket_unemployment_by_region",
 *   label = @Translation("Unemployment by Region"),
 *   description = @Translation("An extra field to display industry unemployment by region."),
 *   bundles = {
 *     "node.labour_market_monthly",
 *   }
 * )
 */
class LabourMarketUnemploymentByRegion extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Unemployment by Region');
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
      $output = '<div>'. WORKBC_EXTRA_FIELDS_NOT_AVAILABLE .'</div>';
      return [
        ['#markup' => $output ],
      ];
    }

    //values
    $current_previous_months = $entity->ssot_data['current_previous_months_names'];

    $header = [' ',  $current_previous_months['current_month_year'] , $current_previous_months['current_month_previous_year']];

    $rows = $this->getRegionValues($entity->ssot_data['monthly_labour_market_updates'][0]);
    
    //Image
    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule('workbc_extra_fields')->getPath();
    $image_uri = '/' . $module_path . '/images/' . WORKBC_BC_MAP_WITH_LABELS;

    //Source
    $source_text = $entity->ssot_data['sources']['no-datapoint'];
    $output = '<span><strong>'.$this->t("Source").': </strong>'.$source_text.'</span>';

    return [
      [
            '#theme' => 'image',
            '#uri' => $image_uri,
            '#alt' => 'BC Image Map',
      ],
      [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#attributes' => array('class'=>array('bc-region-table')),
        '#header_columns' => 4,
      ],
      [
        '#markup' => $output 
      ]
    ];
  }

  public function getRegionValues($values){
    $regions = [];
    $needle = 'unemployment_pct_';
    if(!empty($values)){
      foreach($values as $key => $value){
        if(strpos($key, $needle) !== false){
          $regionsubstring = str_replace('unemployment_pct_', "", $key);
          //region mapping
          $region_map = $this->getRegionMappings();

          //if previous values
          if(strpos($regionsubstring, 'previous') !== false) {
            $regionsubstring = str_replace('_previous', "", $regionsubstring);
            if(empty($region_map[$regionsubstring])){
              continue;
            }
            $regions[$regionsubstring]['region'] = $region_map[$regionsubstring];
            $regions[$regionsubstring]['previous'] = !empty($value)?$value.'%': WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
          } else {
            if(empty($region_map[$regionsubstring])){
              continue;
            }
            $regions[$regionsubstring]['region'] = $region_map[$regionsubstring];
            $regions[$regionsubstring]['current'] = !empty($value)?$value.'%': WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
          }
          
        }
      }
    }
    return $regions;
  }

    public function getRegionMappings(){
    $region_map = [
            'all' => 'All regions',
            'british_columbia' => 'British Columbia',
            'vancouver_island_coast' =>'Vancouver Island/Coast',
            'mainland_southwest'  => 'Mainland/Southwest',
            'thompson_okanagan' => 'Thompson-Okanagan',
            'kootenay' => 'Kootenay',
            'cariboo' => 'Cariboo',
            'north_coast_and_nechako' => 'North Coast and Nechako',
            'northeast' => 'Northeast'
          ];
    return $region_map;
  }


}
