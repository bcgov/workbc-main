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


    $industries = [];

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['census'])) {
      $industry = [];
      $industry['name'] = $entity->ssot_data['census']['industry_1_name'];
      $industry['openings'] = $entity->ssot_data['census']['industry_1'];
      $industries[] = $industry;
      $industry = [];
      $industry['name'] = $entity->ssot_data['census']['industry_2_name'];
      $industry['openings'] = $entity->ssot_data['census']['industry_2'];
      $industries[] = $industry;
      $industry = [];
      $industry['name'] = $entity->ssot_data['census']['industry_3_name'];
      $industry['openings'] = $entity->ssot_data['census']['industry_3'];
      $industries[] = $industry;
      $industry = [];
      $industry['name'] = $entity->ssot_data['census']['industry_4_name'];
      $industry['openings'] = $entity->ssot_data['census']['industry_4'];
      $industries[] = $industry;
      $industry = [];
      $industry['name'] = $entity->ssot_data['census']['industry_5_name'];
      $industry['openings'] = $entity->ssot_data['census']['industry_5'];
      $industries[] = $industry;
    }

    $text = "<div>";
    $text = "<table>";
    $text .= "<tr><th>Industry</th><th>Job Openings (2021-2031)</th></tr>";
    foreach ($industries as $industry) {
      $text .= "<tr><td>" . $industry['name'] . "</td><td>" . number_format($industry['openings']) . "</td></tr>";
    }
    $text .= "</table>";
    $output = $text;

    return [
      ['#markup' => $output],
    ];
  }

}
