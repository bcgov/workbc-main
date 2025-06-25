<?php

namespace Drupal\workbc_custom;

use Drupal\Core\Security\TrustedCallbackInterface;

class ChosenFormRender implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'preRenderSelect',
    ];
  }

  /**
   * Render API callback: Apply Chosen to a select element.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   The element.
   */
  public static function preRenderSelect($element) {
    // Don't process if we're not on Explore Career Sesrch page.
    $path = \Drupal::service('path.current')->getPath();
    $alias = \Drupal::service('path_alias.manager')->getAliasByPath($path);
    $paths = \Drupal::config('workbc')->get('paths');
    if ($alias !== $paths['career_exploration_search']) return $element;

    // Set Chosen's option hide_results_on_select = false to keep the drop-down open on selection.
    if (isset($element['#attached']['drupalSettings']['chosen'])) {
      $element['#attached']['drupalSettings']['chosen']['options']['hide_results_on_select'] = FALSE;
    }
    return $element;
  }
}
