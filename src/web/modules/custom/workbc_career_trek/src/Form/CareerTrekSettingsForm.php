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

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $config->get('title'),
    ];

    $form['logo'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Logo'),
      '#default_value' => $config->get('logo'),
      '#upload_location' => 'public://career_trek_logos/',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg'],
        'file_validate_size' => [25600000], // 25MB max
        'file_validate_name_length' => [255],
      ],
      '#description' => $this->t('Allowed extensions: png, jpg, jpeg. Maximum file size: 25MB.'),
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

    $form['toggle_icon_grid'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Toggle Icon Grid'),
      '#default_value' => $config->get('toggle_icon_grid'),
      '#upload_location' => 'public://career_trek_icons/',
      '#upload_validators' => [
        'file_validate_extensions' => ['svg'],
      ],
    ];

    $form['toggle_icon_list'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Toggle Icon List'),
      '#default_value' => $config->get('toggle_icon_list'),
      '#upload_location' => 'public://career_trek_icons/',
      '#upload_validators' => [
        'file_validate_extensions' => ['svg'],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('workbc_career_trek.settings');
    
    // Save all form values to configuration
    $config->set('title', $form_state->getValue('title'))
          ->set('logo', $form_state->getValue('logo'))
          ->set('back_button_url', $form_state->getValue('url'))
          ->set('back_button_title', $form_state->getValue('title'))
          ->set('toggle_icon_grid', $form_state->getValue('toggle_icon_grid'))
          ->set('toggle_icon_list', $form_state->getValue('toggle_icon_list'))
          ->save();

    parent::submitForm($form, $form_state);
  }

}
