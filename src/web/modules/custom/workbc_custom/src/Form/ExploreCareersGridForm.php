<?php

namespace Drupal\workbc_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ExploreCareersGridForm
 *
 * @package Drupal\workbc_custom\Form;
 */
class ExploreCareersGridForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workbc_custom_explore_careers_grid_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $clean_string_service = \Drupal::service('pathauto.alias_cleaner');
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('epbc_categories');
    $categories = array_filter($terms, function ($term) {
      return $term->depth === 0;
    });
    foreach ($categories as $category) {
      $category_label = $clean_string_service->cleanString($category->name);
      $form[$category_label] = [
        '#type' => 'details',
        '#title' => $category->name,
      ];
      $form[$category_label][$category->tid] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Select all areas of interest'),
        '#attributes' => ['class' => ['grid-all']]
      ];
      $areas = array_filter($terms, function ($term) use ($category) {
        return $term->depth === 1 && $term->parents[0] === $category->tid;
      });
      foreach ($areas as $area) {
        $form[$category_label][$area->tid] = [
          '#type' => 'checkbox',
          '#title' => $area->name,
          '#attributes' => ['class' => ['grid-term']]
        ];
      }
      $form[$category_label]['submit'] = [
        '#type' => 'submit',
        '#value' => t('Explore'),
      ];
    }
    $form['terms'] = [
      '#type' => 'value',
      '#value' => $terms,
    ];
    return $form;
  }

  /**
  * {@inheritdoc}
  */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $terms = $form_state->getValue('terms');
    $categories = array_filter($terms, function ($term) {
      return $term->depth === 0;
    });
    $selection = array_keys(array_filter($form_state->getValues(), function($v, $k) use($categories) {
      return is_int($k) && $v === 1 && !in_array($k, array_column($categories, 'tid'));
    }, ARRAY_FILTER_USE_BOTH));
    $form_state->setRedirect('view.explore_careers.page_1', [], [
      'query' => [
        'hide_category' => 1,
        'field_epbc_categories_target_id' => $selection,
        'term_node_tid_depth' => array_search_func($terms, function($k, $v) use ($selection) {
          return $selection[0] == $v->tid;
        })->parents[0],
      ]
    ]);
  }
}
