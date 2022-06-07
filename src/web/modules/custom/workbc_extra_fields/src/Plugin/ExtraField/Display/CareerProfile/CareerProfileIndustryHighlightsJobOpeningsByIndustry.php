<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "job_openings_by_industry",
 *   label = @Translation("Industry Highlights - Job Openings by Industry"),
 *   description = @Translation("An extra field to display job opening forecast chart."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileIndustryHighlightsJobOpeningsByIndustry extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Forecasted Job Openings by Industry');
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
    for ($i = 0; $i < 3; $i++) {
      $regions[$i] = mt_rand(50000, 100000);
    }

    $text = "<div>";
    $text = "<table>";
    $text .= "<tr><th>Industry</th><th>Job Openings (2019-2029)</th></tr>";
    foreach ($regions as $region) {
      $text .= "<tr><td>[industry-name]</td><td>" . number_format($region) . "</td></tr>";
    }
    $text .= "</table>";
    $output = $text;

    return [
      ['#markup' => $output],
    ];
  }

}
