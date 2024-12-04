<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "search_index_job_titles",
 *   label = @Translation("Search Index Job Titles"),
 *   description = @Translation("An extra field to display job titles for search indexing."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileSearchIndexJobTitles extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('');
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
    if (!empty($entity->ssot_data) && isset($entity->ssot_data['titles'])) {
      $output = '';
      foreach ($entity->ssot_data['titles'] as $key => $jobTitle) {
        $output .= ' ' . $jobTitle['commonjobtitle'];
      }
    }
    return [
      ['#markup' => $output],
    ];
  }

}
