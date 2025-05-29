<?php

namespace Drupal\workbc_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

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

    $arrows = ['arrow-1', 'arrow-2', 'arrow-3', 'arrow-4'];
    $pos = 0;
    foreach ($categories as $key => $category) {
      $areas = array_filter($terms, function ($term) use ($category) {
        return $term->depth === 1 && $term->parents[0] === $category->tid;
      });

      $category_label = $clean_string_service->cleanString($category->name);

      $form[$category_label . "-tile"] = [
        '#markup' => $this->generateTile($category_label, $category->name, count($areas)),
        '#attributes' => [
          'id' => "category-id-$category_label",
          'data-category-id' => $category_label,
        ],
        '#prefix' => '<div id="category-' . $category_label . '" class="grid-item occupational-category" data-category-id="' . $category_label . '">',
        '#suffix' => '</div>',
      ];

      $form[$category_label] = [
        '#type' => 'fieldset',
        '#attributes' => [
          'id' => "selector-$category_label",
          'class' => ['grid-item', 'areas-of-interest', 'fullwidth', 'is-hidden', $arrows[$pos]]
        ],
      ];
      $pos = ($pos + 1) % 4;

      $form[$category_label]['help'] = [
        '#markup' => '<div class="areas-of-interest-help">' . $this->t('Choose areas that interest you within @category', ['@category' => $category->name]) . '</div>',
      ];

      $form[$category_label]['close'] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#prefix' => '<div class="areas-of-interest-close">',
        '#suffix' => '</div>',
        '#attributes' => [
          'type' => 'button',
          'class' => ['btn-close'],
          'aria-label' => $this->t('Close'),
        ]
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
        '#prefix' => '<div class="areas-of-interest-submit">',
        '#suffix' => '<span class="error hidden"><span class="error-group"><span class="error-icon"></span><span class="error-text">' . $this->t('Choose one or more options within @category', ['@category' => $category->name]) . '</span></span></span></div>',
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
      $form_state->setError($form, $this->t('Please select an area of interest before proceeding.'));
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

    $paths = \Drupal::config('workbc')->get('paths');
    $url = URL::fromUserInput($paths['career_exploration_search']);
    $form_state->setRedirect($url->getRouteName(), $url->getRouteParameters(), [
      'query' => [
        'hide_category' => 1,
        'field_epbc_categories_target_id' => $selection,
        'term_node_tid_depth' => array_find($terms, function($v) use ($selection) {
          return $selection[0] == $v->tid;
        })->parents[0],
      ]
    ]);
  }

  private function generateTile($category_label, $category_name, $areasCount) {
    return <<<EOS
    <div class="tile">
      <div class="tile-info tile-icon">
        <img src="/modules/custom/workbc_custom/icons/epbc/$category_label.svg" title="$category_name"/>
      </div>
      <div class="tile-info tile-name">$category_name</div>
      <div class="tile-group">
        <div class="tile-info tile-areas">{$this->t('@count Areas of interest', ['@count' => $areasCount])}</div>
        <div class="tile-info tile-expand">
          <img data-category-id="$category_label" tabindex="0" src="/modules/custom/workbc_custom/icons/expand.svg" alt="expand"/>
        </div>
      </div>
    </div>
    EOS;
  }
}
