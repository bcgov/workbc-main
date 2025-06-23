<?php

namespace Drupal\workbc_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

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
    $tooltip = <<<EOS
      <span class="workbc-tooltip explore-careers--tooltip">
        <div class="workbc-tooltip-content explore-careers--tooltip-content">
          {$this->t('The National Occupational Classification System (NOC) is Canada’s national system for describing occupations. Each occupation is assigned a unique five-digit NOC code.')}
        </div>
      </span>
    EOS;

    $form['keywords'] = [
      '#type' => 'search_api_autocomplete',
      '#title' => $this->t('Find a career profile by job title, occupation title, or NOC code.') . '&nbsp;' . $tooltip,
      '#search_id' => 'explore_careers_autocomplete',
      '#additional_data' => [
        'display' => 'block_1',
        'arguments' => [],
        'filter' => 'search',
      ],
      '#default_value' => $form_state->getValue('keywords'),
      '#attributes' => ['placeholder' => $this->t('Keyword / NOC code')],
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
    $url = URL::fromUserInput('/plan-career/explore-careers/career-profiles/search');
    $keywords = trim($form_state->getValue('keywords'));
    $form_state->setRedirect($url->getRouteName(), $url->getRouteParameters(), [
      'query' => [
        'hide_category' => 0,
        'keyword_search' => $keywords,
        'sort_bef_combine' => empty($keywords) ? 'title_ASC' : 'keyword_search_ASC',
      ]
    ]);
  }
}
