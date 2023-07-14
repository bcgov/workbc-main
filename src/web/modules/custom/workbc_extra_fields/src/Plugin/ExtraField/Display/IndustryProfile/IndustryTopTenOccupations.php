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

      $content = "<table>";
      $content .= "<tr><th>Top Ten Occupations</th><th>Job Openings<br>(" . $datestr . ")</th></tr>";
      foreach ($entity->ssot_data['openings_industry'] as $job) {
        if ($nid = $this->nodeID($job['noc'])) {
          $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/'.$nid);
          $link = "<a href='" . $alias . "'>";
        }
        else {
          $link = "";
        }
        $content .= "<tr>";
        $content .= "<td>" . $link . $job['description'] . " (NOC " . $job['noc'] . ")</a></td>";
        $content .= "<td>" . ssotFormatNumber($job['openings'],0) . "</td>";
      }
      $content .= "</table>";
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
        ->condition('field_noc.value', $noc);
    $nids = $query->execute();
    return !empty($nids) ? reset($nids) : false;
  }
}
