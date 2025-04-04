<?php

namespace Drupal\workbc_custom\Plugin\views\filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\Plugin\views\filter\TaxonomyIndexTid;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\taxonomy\VocabularyStorageInterface;

/**
 * Filter by term id using hierarchical select widgets.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("workbc_custom_taxonomy_index_tid")
 */
class WorkBCTaxonomyIndexTid extends TaxonomyIndexTid {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, VocabularyStorageInterface $vocabulary_storage, TermStorageInterface $term_storage, ?AccountInterface $current_user = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $vocabulary_storage, $term_storage, $current_user);
  }

  /**
   * {@inheritdoc}
   */
  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildExtraOptionsForm($form, $form_state);

    $form['type']['#options']['epbc'] = $this->t('EPBC Categories selector');
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $vocabulary = $this->termStorage->loadTree('epbc_categories');
    if (empty($vocabulary) && $this->options['limit']) {
      $form['markup'] = [
        // cspell:disable-next-line ForbiddenWords
        '#markup' => '<div class="js-form-item form-item">' . $this->t('An invalid vocabulary is selected. Please change it in the options.') . '</div>',
      ];
      return;
    }

    if (($this->options['type'] !== 'epbc') || !$form_state->get('exposed')) {
      // Stop further processing if the filter should not be rendered as exposed
      // filter or as Simple hierarchical select widget.
      return;
    }

    // Get default value for parent and child.
    $default_value = (array) $this->value;
    if (empty($default_value)) {
      $identifier = $this->options['expose']['identifier'];
      $exposed_input = $this->view->getExposedInput()[$identifier] ?? [];
      if ($exposed_input) {
        $default_value = (array) $exposed_input;
      }
    }
    $default_category = !empty($default_value) ? $this->getParentTid($default_value[0], $vocabulary) : null;
    if (empty($default_category)) {
      $identifier = 'category';
      $exposed_input = $this->view->getExposedInput()[$identifier] ?? null;
      if ($exposed_input) {
        $default_category = $exposed_input;
      }
    }

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Occupational Categories'),
      '#options' => array_column(array_filter($vocabulary, function($v) { return $v->depth === 0; }), 'name', 'tid'),
      '#default_value' => $default_category,
      '#ajax' => [
        'callback' => [self::class, 'categoryCallback'],
        'wrapper' => 'category-container',
      ],
    ];

    $form['value'] = [
      '#type' => 'select',
      '#multiple' => true,
      '#title' => $this->t('Areas of Interest'),
      '#options' => array_column(array_filter($vocabulary, function($v) use ($default_category) { return $v->parents[0] == $default_category; }), 'name', 'tid'),
      '#default_value' => $default_value,
      '#prefix' => '<div id="category-container">',
      '#suffix' => '</div>',
      '#chosen' => true,
    ];
  }

  public static function categoryCallback(array &$form, FormStateInterface $form_state) {
    return $form['field_epbc_categories_target_id'];
  }


  private function getParentTid($tid, $vocabulary) {
    $term = array_search_func($vocabulary, function ($k, $v) use ($tid) {
      return $v->tid == $tid;
    });
    return $term ? $term->parents[0] : null;
  }

}
