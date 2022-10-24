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


    //values
    $currentYear = $entity->ssot_data['monthly_labour_market_updates'][0]['year'];
    //month
    $currentMonth = $entity->ssot_data['monthly_labour_market_updates'][0]['month'];
    $currentMonthName = date ('F', mktime(0, 0, 0, $currentMonth, 10));

    $previousMonth = $currentMonth - 1;
    $previousYear = $currentYear;
    if($previousMonth == 0) {
      $previousMonth = 12;
      $previousYear = $currentYear - 1;
    }

    $previousMonthName =  date ('F', mktime(0, 0, 0, $previousMonth, 10));;

    $header = [$this->t(''), $this->t("@curmonth @curyear", ["@curmonth" => $currentMonthName, "@curyear" => $currentYear]), $this->t("@premonth @preyear", ["@premonth" => $previousMonthName, "@preyear" => $previousYear])];

    $rows = $this->getRegionValues($entity->ssot_data['monthly_labour_market_updates'][0]);
    
    //Image
    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule('workbc_extra_fields')->getPath();
    $image_uri = '/' . $module_path . '/images/' . WORKBC_BC_MAP_WITH_LABELS;

    //Source
    $source_text = $entity->ssot_data['sources']['no-datapoint'];
    $output = '<span><strong>'.$this->t("Source: ").'</strong>'.$source_text.'</span>';

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
            $regions[$regionsubstring]['previous'] = $value.'%';
          } else {
            if(empty($region_map[$regionsubstring])){
              continue;
            }
            $regions[$regionsubstring]['region'] = $region_map[$regionsubstring];
            $regions[$regionsubstring]['current'] = $value.'%';
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
