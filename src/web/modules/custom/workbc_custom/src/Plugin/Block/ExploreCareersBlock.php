<?php

namespace Drupal\workbc_custom\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\views\Views;
use Drupal\Core\Form\FormState;

/**
 * Provides a WorkBC Related topics Block.
 *
 * @Block(
 *   id = "explore_careers_block",
 *   admin_label = @Translation("WorkBC explore careers block"),
 *   category = @Translation("WorkBC"),
 * )
 */
class ExploreCareersBlock extends BlockBase {

  /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state) {
      $form = parent::blockForm($form, $form_state);

      $config = $this->getConfiguration();

      $form['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Body'),
        '#description' => $this->t(''),
        '#default_value' => $config['title'] ?? '',
      ];
      $form['body'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Title'),
        '#description' => $this->t(''),
        '#default_value' => $config['body'] ?? '',
      ];

      $form['title_1'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title 1'),
        '#description' => $this->t(''),
        '#default_value' => $config['title_1'] ?? '',
      ];
      $form['body_1'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Body 1'),
        '#description' => $this->t(''),
        '#default_value' => $config['body_1'] ?? '',
      ];
      $form['tooltip_1'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Tooltip 1'),
        '#description' => $this->t(''),
        '#default_value' => $config['tooltip_1'] ?? '',
      ];

      $form['title_2'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title 2'),
        '#description' => $this->t(''),
        '#default_value' => $config['title_2'] ?? '',
      ];
      $form['body_2'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Body 2'),
        '#description' => $this->t(''),
        '#default_value' => $config['body_2'] ?? '',
      ];

      $form['label_2'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Action 2 Label'),
        '#description' => $this->t(''),
        '#default_value' => $config['label_2'] ?? '',
      ];

      return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state) {
      parent::blockSubmit($form, $form_state);
      $values = $form_state->getValues();
      $this->configuration['title'] = $values['title'];
      $this->configuration['body'] = $values['body'];
      $this->configuration['title_1'] = $values['title_1'];
      $this->configuration['body_1'] = $values['body_1'];
      $this->configuration['tooltip_1'] = $values['tooltip_1'];
      $this->configuration['title_2'] = $values['title_2'];
      $this->configuration['body_2'] = $values['body_2'];
      $this->configuration['label_2'] = $values['label_2'];

    }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $explore_careers = [];

    $config = $this->getConfiguration();


    $options = [];
    $link = Link::fromTextAndUrl(t($config['label_2'] ?? ""), Url::fromUri('internal:/plan-career/explore-careers/career-search', $options))->toString();

    $explore_careers = array(
      'title' => $config['title'] ?? "",
      'body' => $config['body'] ?? "",
      'title_1' => $config['title_1'] ?? "",
      'body_1' => $config['body_1'] ?? "",
      'action_1' => $this->renderExposedViewsFilter(),
      'tooltip_1' => $config['tooltip_1'] ?? "",
      'title_2' => $config['title_2'] ?? "",
      'body_2' => $config['body_2'] ?? "",
      'action_2' => $link,
    );

    $renderable = [
      '#theme' => 'explore_careers_block',
      '#explore_careers' => $explore_careers,
    ];

    return $renderable;
  }


  private function renderExposedViewsFilter() {
    $view_id = 'search_career_profiles';
    $display_id = 'page_1';

    $view = Views::getView($view_id);
    $view->setDisplay($display_id);
    $view->initHandlers();
    $form_state = new FormState();
    $form_state->setFormState([
      'view' => $view,
      'display' => $view->display_handler->display,
      'exposed_form_plugin' => $view->display_handler->getPlugin('exposed_form'),
      'rerender' => TRUE,
      'no_redirect' => TRUE,
      'always_process' => TRUE,
    ]);
    $form_state->setMethod('get');
    $form = \Drupal::formBuilder()->buildForm('Drupal\views\Form\ViewsExposedForm', $form_state);
    return $form;
  }

}
