<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\IndustryProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "industry_top_ten_occupations",
 *   label = @Translation("Top Ten Occupations"),
 *   description = @Translation("An extra field to display industry top ten occupations."),
 *   bundles = {
 *     "node.industry_profile",
 *   }
 * )
 */
class IndustryTopTenOccupations extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Top Ten Occupations');
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

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['openings_industry'])) {

      $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'openings_industry', 'openings');

      $options = array(
        'decimals' => 0,
        'na_if_empty' => TRUE,
      );
      $content = "<table>";
      $content .= "<tr><th>Top Ten Occupations</th><th class='top-ten-job-openings data-align-right'>Job Openings<br>(" . $datestr . ")</th></tr>";
      foreach ($entity->ssot_data['openings_industry'] as $job) {
        if ($nid = $this->nodeID($job['noc'])) {
          $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/'.$nid);
          $link = "<a href='" . $alias . "'>";
        }
        else {
          $link = "";
        }
        $content .= "<tr>";
        $content .= "<td class='data-align-left'>" . $link . $job['description'] . " (NOC " . $job['noc'] . ")</a></td>";
        $content .= "<td class='data-align-right'>" . ssotFormatNumber($job['openings'], $options) . "</td>";
      }
      $content .= "</table>";
      $content .= "<a class='btn-primary industry-profile-hoo-link' href='/research-labour-market/high-opportunity-occupations'>View all high opportunity occupations</a>";
      $output = $content;
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }

    return [
      ['#markup' => $output],
    ];
  }

  private function nodeID($noc) {
    $query = \Drupal::entityQuery('node')
        ->condition('status', 1)
        ->condition('field_noc.value', $noc)
        ->accessCheck(false);
    $nids = $query->execute();
    return !empty($nids) ? reset($nids) : false;
  }
}
