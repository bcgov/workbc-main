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

    mt_srand($entity->id());
    $openings = mt_rand(5000, 33000);

    $names = ["Cariboo", "Kootenay", "Mainland/Southwest", "Nort Coast & Nechako", "Northeast", "Thompson-Okanafan", "Vancouver Island-Coast"];
    $regions = [];
    for ($i = 0; $i < 7; $i++) {
      $regions[$i]['name'] = $names[$i];
      $regions[$i]['employment'] = mt_rand(50000, 100000);
      $regions[$i]['percent'] = mt_rand(100, 600) / 10;
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
