<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "labour_market_region_employment",
 *   label = @Translation("Labour Market Info - Employment by Region"),
 *   description = @Translation("An extra field to display job opening forecast chart."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileLabourMarketEmploymentByRegion extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Employment by Region');
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

    $regions = [];
    $options1 = array(
      'decimals' => 1,
      'suffix' => "%",
      'na_if_empty' => TRUE,
    );
    $options2 = array(
      'decimals' => 0,
      'na_if_empty' => TRUE,
    );
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['census']) && isset($entity->ssot_data['career_regional'])) {
      $region = array();
      $region['name'] = t(REGION_CARIBOO);
      $value = $entity->ssot_data['census']['cariboo_employment_of_this_occupation'];
      $region['percent'] = ssotFormatNumber($value, $options1);
      $value = $entity->ssot_data['census']['cariboo_employed_in_this_occupation'];
      $region['employment'] = ssotFormatNumber($value, $options2);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_KOOTENAY);
      $value = $entity->ssot_data['census']['kootenay_employment_of_this_occupation'];
      $region['percent'] = ssotFormatNumber($value, $options1);
      $value = $entity->ssot_data['census']['kootenay_employed_in_this_occupation'];
      $region['employment'] = ssotFormatNumber($value, $options2);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_MAINLAND_SOUTHWEST);
      $value = $entity->ssot_data['census']['mainland_southwest_employment_of_this_occupation'];
      $region['percent'] = ssotFormatNumber($value, $options1);
      $value = $entity->ssot_data['census']['mainland_southwest_employed_in_this_occupation'];
      $region['employment'] = ssotFormatNumber($value, $options2);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_NORTH_COAST_NECHAKO);
      $value = $entity->ssot_data['census']['north_coast_nechako_employment_of_this_occupation'];
      $region['percent'] = ssotFormatNumber($value, $options1);
      $value = $entity->ssot_data['census']['north_coast_nechako_employed_in_this_occupation'];
      $region['employment'] = ssotFormatNumber($value, $options2);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_NORTHEAST);
      $value = $entity->ssot_data['census']['northeast_employment_of_this_occupation'];
      $region['percent'] = ssotFormatNumber($value, $options1);
      $value = $entity->ssot_data['census']['northeast_employed_in_this_occupation'];
      $region['employment'] = ssotFormatNumber($value, $options2);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_THOMPSON_OKANAGAN);
      $value = $entity->ssot_data['census']['thompson_okanagan_employment_of_this_occupation'];
      $region['percent'] = ssotFormatNumber($value, $options1);
      $value = $entity->ssot_data['census']['thompson_okanagan_employed_in_this_occupation'];
      $region['employment'] = ssotFormatNumber($value, $options2);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_VANCOUVER_ISLAND_COAST);
      $value = $entity->ssot_data['census']['vancouver_island_coast_employment_of_this_occupation'];
      $region['percent'] = ssotFormatNumber($value, $options1);
      $value = $entity->ssot_data['census']['vancouver_island_coast_employed_in_this_occupation'];
      $region['employment'] = ssotFormatNumber($value, $options2);
      $regions[] = $region;
    }

    $header = ["Region", "Employment", "% Employment of this Occupation"];

    $rows = [];
    foreach ($regions as $region) {
      $rows[] = [
        'data' => [$region['name'], $region['employment'], $region['percent']],
        'class' => 'interactive-map-row-'.ssotRegionKey($region['name']),
      ];
    }

    $table = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    );
    return $table;
  }

}
