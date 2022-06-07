<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "job_openings_by_region",
 *   label = @Translation("Labour Market Info - Job Openings by Region"),
 *   description = @Translation("An extra field to display job opening forecast chart."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileJobOpeningsByRegion extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Job Openings');
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

    $names = ["Cariboo", "Kootenay", "Mainland/Southwest", "Nort Coast & Nechako", "Northeast", "Thompson-Okanafan", "Vancouver Island-Coast"];
    $regions = [];
    for ($i = 0; $i < 7; $i++) {
      $regions[$i]['name'] = $names[$i];
      $regions[$i]['openings'] = mt_rand(500, 25000);
      $regions[$i]['percent'] = mt_rand(5, 20) / 10;
    }

    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule('workbc_extra_fields')->getPath();

    $text = '<div><img src="/' . $module_path . '/images/u6137.png" width="400px" height="330px"></div>';
    $text .= "<table>";
    $text .= "<tr><th>Region</th><th>Job Openings</th><th>Avg Annual Employment Growth</th></tr>";
    foreach ($regions as $region) {
      $text .= "<tr><td>" . $region['name'] . "</td><td>" . number_format($region['openings']) . "</td><td>" . number_format($region['percent'],1) . "%</td></tr>";
    }
    $text .= "</table>";
    $output = $text;

    return [
      ['#markup' => $output],
    ];
  }

}
