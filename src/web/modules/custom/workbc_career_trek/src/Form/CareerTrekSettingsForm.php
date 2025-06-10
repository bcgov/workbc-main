<?php

namespace Drupal\workbc_career_trek\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class CareerTrekSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'workbc_career_trek.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'workbc_career_trek_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('workbc_career_trek.settings');

    // Get the path to the module.
    $moduleHandler = \Drupal::service('module_handler');
    $modulePath = '/' . $moduleHandler->getModule('workbc_career_trek')->getPath();

    $default_logo_path = $modulePath . '/assets/images/carrerTrekLogo.png';
    $default_icon_grid_path = $modulePath . '/assets/icons/grid_icon.svg';
    $default_list_icon_path = $modulePath . '/assets/icons/list_icon.svg';
    $default_toggle_icon_path = $modulePath . '/assets/icons/close.svg';

    $form['main_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $config->get('main_title'),
    ];

    $form['logo'] = [
      '#type' => 'hidden',
      '#value' => $default_logo_path,
    ];

    $form['toggle_icon_grid'] = [
      '#type' => 'hidden',
      '#value' => $default_icon_grid_path,
    ];

    $form['toggle_icon_list'] = [
      '#type' => 'hidden',
      '#value' => $default_list_icon_path,
    ];

    $form['responsive_toggle_icon'] = [
      '#type' => 'hidden',
      '#value' => $default_toggle_icon_path,
    ];

    $form['back_button'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Back Button'),
    ];

    $form['back_button']['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Back Button URL'),
      '#default_value' => $config->get('back_button_url'),
      '#description' => $this->t('Enter the URL for the back button'),
    ];

    $form['back_button']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Back Button Title'),
      '#default_value' => $config->get('back_button_title'),
      '#description' => $this->t('Enter the text to display on the back button'),
    ];

    $form['searching_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Searching text'),
      '#default_value' => $config->get('searching_text'),
      '#description' => $this->t('Enter the searching text'),
    ];
    $form['in_demand_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('In Demand Title'),
      '#default_value' => $config->get('in_demand_title'),
      '#description' => $this->t('Enter the demand title'),
    ];
    $form['latest_career_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Latest Career Title'),
      '#default_value' => $config->get('latest_career_title'),
      '#description' => $this->t('Enter the latest career title'),
    ];
    $form['filter_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filter Title'),
      '#default_value' => $config->get('filter_title'),
      '#description' => $this->t('Enter the Filter title'),
    ];
    $form['related_careers_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Related Title'),
      '#default_value' => $config->get('related_careers_title'),
      '#description' => $this->t('Enter the Related title'),
    ];

    $form['url_skills_future_workforce'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Skill Future Url'),
      '#default_value' => $config->get('url_skills_future_workforce'),
      '#description' => $this->t('Enter the Related title'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('workbc_career_trek.settings');

    // Save all form values, using the hidden value for managed_file fields
    $fields = [
      'main_title' => 'main_title',
      'logo' => 'logo',
      'back_button_url' => 'url',
      'back_button_title' => 'title',
      'toggle_icon_grid' => 'toggle_icon_grid',
      'toggle_icon_list' => 'toggle_icon_list',
      'searching_text' => 'searching_text',
      'in_demand_title' => 'in_demand_title',
      'latest_career_title' => 'latest_career_title',
      'filter_title' => 'filter_title',
      'responsive_toggle_icon' => 'responsive_toggle_icon',
      'related_careers_title' => 'related_careers_title',
      'url_skills_future_workforce' => 'url_skills_future_workforce',
    ];
    foreach ($fields as $formKey => $configKey) {
      $config->set($formKey, $form_state->getValue($configKey));
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
