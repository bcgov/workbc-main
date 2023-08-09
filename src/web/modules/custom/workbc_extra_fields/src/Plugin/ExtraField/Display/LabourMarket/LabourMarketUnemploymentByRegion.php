<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\LabourMarket;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Link;
use Drupal\Core\Url;
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

    ksm(ssotRegionInfo());
    
    $content = "";
    $content .= "<table class='lm-table-region table'>";
    $content .= "<thead>";
    $content .= "<tr class='lm-header'><th></th><th>" . $current_previous_months['current_month_year'] . "</th><th>" . $current_previous_months['current_month_previous_year'] . "</th></tr>";
    $content .= "</thead>";

    foreach ($rows as $key => $region) {
      $content .= "<tr class='interactive-map-row-" . $key . "'>";
      $content .= "<td class='region-name'>" . $region['region'] . "</td>";
      $content .= "<td>" . $region['current'] . "</td>";
      $content .= "<td>" . $region['previous'] . "</td>";
      $content .= "</tr>";      
    }

    $content .= "</table>";

    //Source
    $source_text = !empty($entity->ssot_data['sources']['unemployment_pct'])?$entity->ssot_data['sources']['unemployment_pct']:WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    $content .= '<div class="lm-source"><strong>'.$this->t("Source").': </strong>'.$source_text.'</div>';


    return [
        '#markup' => $content,
      ];

    $rows = [];
    foreach ($data as $key => $region) {
      $id='interactive-map-row-' . $key;
			$rows[] = array('data' => $region, 'id' => $id);
    }

  }

  public function getRegionValues($values){
    $regions = [];
    $needle = 'unemployment_pct_';

    $options = array(
      'decimals' => 0,
      'suffix' => '%',
      'na_if_empty' => TRUE,
    );

    if(!empty($values)){
      foreach($values as $key => $value){
        if(strpos($key, $needle) !== false){
          $regionsubstring = str_replace('unemployment_pct_', "", $key);
          //region mapping
          $region_map = getRegionMappings();
          //if previous values
          if(strpos($regionsubstring, 'previous') !== false) {
            $regionsubstring = str_replace('_previous', "", $regionsubstring);
            if(empty($region_map[$regionsubstring])){
              continue;
            }
            $regions[$regionsubstring]['region'] = Link::fromTextAndUrl(t($region_map[$regionsubstring]), Url::fromUri('internal:' . ssotRegionLink($regionsubstring), []))->toString();
            $regions[$regionsubstring]['previous'] = !empty($value)?$value.'%': WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
          } else {
            if(empty($region_map[$regionsubstring])){
              continue;
            }
            $regions[$regionsubstring]['region'] = Link::fromTextAndUrl(t($region_map[$regionsubstring]), Url::fromUri('internal:' . ssotRegionLink($regionsubstring), []))->toString();
            $regions[$regionsubstring]['current'] = !empty($value)?$value.'%': WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
          }

        }
      }
    }
    return $regions;
  }


}
