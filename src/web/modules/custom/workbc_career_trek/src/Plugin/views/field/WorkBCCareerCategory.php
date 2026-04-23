<?php

namespace Drupal\workbc_career_trek\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * A handler to display top-level career categories.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("workbc_career_categories")
 */
class WorkBCCareerCategory extends FieldPluginBase {

  static $epbc_tree;

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    if (!isset(self::$epbc_tree)) {
      self::$epbc_tree = array_reduce(\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('epbc_categories'), function ($tree, $term) {
        if ($term->depth > 0) {
          $parent_tid = reset($term->parents);
          $tree[$term->tid] = $tree[$parent_tid];
        }
        else {
          $tree[$term->tid] = $term->name;
        }
        return $tree;
      }, []);
    }

    if (array_key_exists('reverse__node__field_career_videos', $values->_relationship_entities)) {
      return join(', ', array_unique(
        array_map(function($tid) {
          return self::$epbc_tree[$tid];
        }, array_column($values->_relationship_entities['reverse__node__field_career_videos']->field_epbc_categories->getValue(), 'target_id'))
      ));
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // This function exists to override parent query function.
    // Do nothing.
  }
}
