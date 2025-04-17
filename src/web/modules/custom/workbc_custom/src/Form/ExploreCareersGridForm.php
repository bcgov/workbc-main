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
      $areas = array_filter($terms, function ($term) use ($category) {
        return $term->depth === 1 && $term->parents[0] === $category->tid;
      });

      $category_label = $clean_string_service->cleanString($category->name);
      $form[$category_label . "-tile"] = [
        '#markup' => $this->generateTile($category->name, count($areas)),
        '#attributes' => ['id' => 'category-id-'.$category_label,
                          'data-category-id' => $category_label,
        ],
        '#prefix' => '<div id="category-' . $category_label . '" class="grid-item" data-category-id="' . $category_label . '">',
        '#suffix' => '</div>',
      ];
      
      $form[$category_label] = [
        '#type' => 'fieldset',
        '#attributes' => ['id' => 'selector-'.$category_label,
                          'class' => ['fullwidth', 'is-hidden'],
        ],
      ];
      $form[$category_label][$category->tid] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Select all areas'),
        '#attributes' => ['class' => ['grid-all']]
      ];

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
        '#suffix' => '<span class="error hidden">Please select an area of interest before proceeding.</span>',
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $selection = array_keys(array_filter($form_state->getValues(), function($v, $k) {
      return is_int($k) && $v === 1;
    }, ARRAY_FILTER_USE_BOTH));
    if (empty($selection)) {
      $form_state->setError($form, 'Please select an area of interest before proceeding.');
    }
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
        'term_node_tid_depth' => array_find($terms, function($v) use ($selection) {
          return $selection[0] == $v->tid;
        })->parents[0],
      ]
    ]);
  }


  private function generateTile($label, $areasCount) {
    $markup = '<div class="tile">';
    $markup .= '<div class="tile-info tile-icon">[icon]</div>';
    $markup .= '<div class="tile-info tile-label">' . $label . '</div>';
    $markup .= '<div class="tile-info tile-areas">' . $areasCount . " areas of interest</div>";
    $markup .= '<div class="tile-info tile-plus">+</div>';
    $markup .= '</div>';
    return $markup;
  }
}
