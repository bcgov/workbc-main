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

    $names = ["Cariboo", "Kootenay", "Mainland/Southwest", "Nort Coast & Nechako", "Northeast", "Thompson-Okanagan", "Vancouver Island-Coast"];
    $regions = [];
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['career_regional'])) {
      $region = array();
      $region['name'] = t(REGION_CARIBOO);
      $region['employment'] = intval($entity->ssot_data['career_regional']['cariboo_employment_in_2021']);
      $region['percent'] = floatval($entity->ssot_data['census']['cariboo_employment_of_this_occupation']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_KOOTENAY);
      $region['employment'] = intval($entity->ssot_data['career_regional']['kootenay_employment_in_2021']);
      $region['percent'] = floatval($entity->ssot_data['census']['kootenay_employment_of_this_occupation']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_MAINLAND_SOUTHWEST);
      $region['employment'] = intval($entity->ssot_data['career_regional']['mainland_southwest_employment_in_2021']);
      $region['percent'] = floatval($entity->ssot_data['census']['mainland_southwest_employment_of_this_occupation']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_NORTH_COAST_NECHAKO);
      $region['employment'] = intval($entity->ssot_data['career_regional']['north_coast_and_nechako_employment_in_2021']);
      $region['percent'] = floatval($entity->ssot_data['census']['north_coast_nechako_employment_of_this_occupation']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_NORTHEAST);
      $region['employment'] = intval($entity->ssot_data['career_regional']['northeast_employment_in_2021']);
      $region['percent'] = floatval($entity->ssot_data['census']['northeast_employment_of_this_occupation']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_THOMPSON_OKANAGAN);
      $region['employment'] = intval($entity->ssot_data['career_regional']['thompson_okanagan_employment_in_2021']);
      $region['percent'] = floatval($entity->ssot_data['census']['thompson_okanagan_employment_of_this_occupation']);
      $regions[] = $region;
      $region = array();
      $region['name'] = t(REGION_VANCOUVER_ISLAND_COAST);
      $region['employment'] = intval($entity->ssot_data['career_regional']['vancouver_island_coast_employment_in_2021']);
      $region['percent'] = floatval($entity->ssot_data['census']['vancouver_island_coast_employment_of_this_occupation']);
      $regions[] = $region;
    }

    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule('workbc_extra_fields')->getPath();

    $text = '<div><img src="/' . $module_path . '/images/u6137.png" width="400px" height="330px"></div>';
    $text .= "<div>";
    $text .= "<table>";
    $text .= "<tr><th>Region</th><th>Employment (2019)</th><th>% Employment</th></tr>";
    foreach ($regions as $region) {
      $text .= "<tr><td>" . $region['name'] . "</td><td>" . number_format($region['employment']) . "</td><td>" . number_format($region['percent'],1) . "%</td></tr>";
    }
    $text .= "</table>";
    $text .= "</div>";

    $output = $text;

    return [
      ['#markup' => $output],
    ];
  }

}
