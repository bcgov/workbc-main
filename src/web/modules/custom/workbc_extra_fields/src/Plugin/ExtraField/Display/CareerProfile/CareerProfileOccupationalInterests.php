<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "occupational_interests",
 *   label = @Translation("Occupational Interests"),
 *   description = @Translation("Occupational Interests"),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileOccupationalInterests extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Occupational Interests');
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

    $output = [];
    if (!empty($entity->ssot_data) && !empty($entity->ssot_data['occupational_interests'])) {
        foreach ($entity->ssot_data['occupational_interests'] as $interest) {
            $entity_type = 'taxonomy_term';
            $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->loadByProperties([
                'vid' => 'occupational_interests',
                'name' => $interest['occupational_interest']
            ]);
            if (empty($entity)) {
                \Drupal::logger('workbc_extra_fields')->error("Could not find occupational interest labeled {$interest['occupational_interest']}.");
                continue;
            }
            $view_builder = \Drupal::entityTypeManager()->getViewBuilder($entity_type);
            $pre_render = $view_builder->view(current(array_values($entity)), 'full');
            $output[] = \Drupal::service('renderer')->render($pre_render);
        }
    }
    return [
      array_map(function($o) { return ['#markup' => $o]; }, $output),
    ];
  }

}
