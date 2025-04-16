<?php

namespace Drupal\workbc_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ExploreCareersSearchForm
 *
 * @package Drupal\workbc_custom\Form;
 */
class ExploreCareersSearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workbc_custom_explore_careers_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['text'] = [
      '#markup' => 'Find a career profile by job title, occupation title, or NOC code (i)',
    ];
    $form['keywords'] = [
      '#type' => 'search_api_autocomplete',
      '#search_id' => 'explore_careers_autocomplete',
      '#additional_data' => [
        'display' => 'block_1',
        'arguments' => [],
        'filter' => 'search',
      ],
      '#default_value' => $form_state->getValue('keywords'),
      '#help' => $this->t('Enter a keyword or NOC code'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
    ];
    return $form;
  }

  /**
  * {@inheritdoc}
  */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('view.explore_careers.page_1', [], [
      'query' => [
        'hide_category' => 0,
        'keyword_search' => $form_state->getValue('keywords'),
      ]
    ]);
  }
}
