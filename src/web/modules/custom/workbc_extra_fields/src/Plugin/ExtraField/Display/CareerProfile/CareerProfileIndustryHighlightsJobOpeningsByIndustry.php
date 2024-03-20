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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['openings_careers'])) {
      if (!is_null($entity->ssot_data['openings_careers']['industry_1_percent'])) {
        $industry = [];
        $industry['name'] = $entity->ssot_data['openings_careers']['industry_1_name'];
        $industry['openings_careers'] = $entity->ssot_data['openings_careers']['industry_1_openings'];
        $industries[] = $industry;
      }
      if (!is_null($entity->ssot_data['openings_careers']['industry_2_percent'])) {
        $industry = [];
        $industry['name'] = $entity->ssot_data['openings_careers']['industry_2_name'];
        $industry['openings_careers'] = $entity->ssot_data['openings_careers']['industry_2_openings'];
        $industries[] = $industry;
      }
      if (!is_null($entity->ssot_data['openings_careers']['industry_3_percent'])) {
        $industry = [];
        $industry['name'] = $entity->ssot_data['openings_careers']['industry_3_name'];
        $industry['openings_careers'] = $entity->ssot_data['openings_careers']['industry_3_openings'];
        $industries[] = $industry;
      }
      if (!is_null($entity->ssot_data['openings_careers']['industry_4_percent'])) {
        $industry = [];
        $industry['name'] = $entity->ssot_data['openings_careers']['industry_4_name'];
        $industry['openings_careers'] = $entity->ssot_data['openings_careers']['industry_4_openings'];
        $industries[] = $industry;
      }
      if (!is_null($entity->ssot_data['openings_careers']['industry_5_percent'])) {
        $industry = [];
        $industry['name'] = $entity->ssot_data['openings_careers']['industry_5_name'];
        $industry['openings_careers'] = $entity->ssot_data['openings_careers']['industry_5_openings'];
        $industries[] = $industry;
      }
    }

    if (!empty($industries)) {
      $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'openings_careers', 'industry_1_openings');

      $options = array(
        'na_if_empty' => TRUE,
      );
      $text = "<div>";
      $text = "<table>";
      $text .= "<tr><th>Industry</th><th class='data-align-right'>Job Openings (" . $datestr . ")</th></tr>";
      foreach ($industries as $industry) {
        if ($industry['openings_careers'] > 0) {
          $text .= "<tr><td>" . $industry['name'] . "</td><td class='data-align-right'>" . ssotFormatNumber($industry['openings_careers'], $options) . "</td></tr>";
        }
      }
      $text .= "</table>";
      $output = $text;
    }
    else {
      $output = "<div class='career-forecasted-by-industry-not-available'>" . WORKBC_EXTRA_FIELDS_DATA_NOT_AVAILABLE . "</div>";
    }

    return [
      ['#markup' => $output],
    ];
  }

}
