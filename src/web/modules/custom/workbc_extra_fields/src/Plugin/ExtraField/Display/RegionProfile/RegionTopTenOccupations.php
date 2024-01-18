<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\RegionProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "region_top_ten_occupations",
 *   label = @Translation("Top Ten Occupations"),
 *   description = @Translation("An extra field to display top ten occupatoins."),
 *   bundles = {
 *     "node.region_profile",
 *   }
 * )
 */
class RegionTopTenOccupations extends ExtraFieldDisplayFormattedBase {

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

    $options = array(
      'decimals' => 0,
      'na_if_empty' => TRUE,
    );
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['regional_top_occupations'])) {
      $datestr = ssotParseDateRange($this->getEntity()->ssot_data['schema'], 'regional_top_occupations', 'openings');
      $content = "<table>";
      $content .= "<tr><th>Top Ten Occupations</th><th class='data-align-right'>Job Openings (" . $datestr . ")</th></tr>";
      foreach ($entity->ssot_data['regional_top_occupations'] as $job) {
        if ($nid = $this->nodeID($job['noc'])) {
          $alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/'.$nid);
          $link = "<a href='" . $alias . "'>";
        }
        else {
          $link = "";
        }
        $content .= "<tr>";
        $content .= "<td class='data-align-left'>" . $link . $job['occupation'] . " (NOC " . $job['noc'] . ")</a></td>";
        $content .= "<td class='data-align-right'>" . ssotFormatNumber($job['openings'], $options) . "</td>";
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
