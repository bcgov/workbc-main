<?php

namespace Drupal\workbc_extra_fields\Plugin\ExtraField\Display\CareerProfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;

/**
 * Example Extra field with formatted output.
 *
 * @ExtraFieldDisplay(
 *   id = "occupational_categories",
 *   label = @Translation("[SSOT] Occupational Categories"),
 *   description = @Translation("An extra field to display career occupational categories."),
 *   bundles = {
 *     "node.career_profile",
 *   }
 * )
 */
class CareerProfileOccupationalCategories extends ExtraFieldDisplayFormattedBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getLabel() {

    return $this->t('Occupational Categories');
  }

  /**
   * {@inheritdoc}
   */
  public function getLabelDisplay() {

    return 'hidden';
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {

    if (!empty($entity->ssot_data) && isset($entity->ssot_data['occupational_category'])) {
      $exposed_input = parse_url(\Drupal::request()->getRequestUri(), PHP_URL_QUERY);
      parse_str($exposed_input ?? '', $args);

      // Preload the EPBC/FYP categories if needed.
      $epbc_tree = [];
      if (array_key_exists('field_epbc_categories_target_id', $args)) {
        $epbc_tree = array_reduce(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('epbc_categories'),
        function ($epbc_tree, $term) {
          if ($term->depth == 0) {
            $epbc_tree[strtolower(trim($term->name))] = $term->tid;
          }
          return $epbc_tree;
        }, []);
      }

      $output = join(', ', array_unique(array_map(function ($category) {
          return $category['category'];
        }, array_filter($entity->ssot_data['occupational_category'], function ($category) use ($args, $epbc_tree) {
          return !array_key_exists('field_epbc_categories_target_id', $args) || array_key_exists($epbc_tree[strtolower(trim($category['category']))], $args['field_epbc_categories_target_id']);
        })
      )));
    }
    else {
      $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
    }
    return [
      ['#markup' => $output],
    ];
  }

}
