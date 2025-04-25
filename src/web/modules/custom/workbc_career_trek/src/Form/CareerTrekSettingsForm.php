<?php

namespace Drupal\workbc_career_trek\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

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

    $form['main_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $config->get('main_title'),
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
    $form['responsive_toggle_icon'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Responsive Toggle Icon'),
      '#default_value' => $config->get('responsive_toggle_icon'),
      '#upload_location' => 'public://career_trek_icons/',
      '#upload_validators' => [
        'file_validate_extensions' => ['svg'],
      ],
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

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('workbc_career_trek.settings');
    
    // Handle file updates
    $fileFields = ['logo', 'toggle_icon_grid', 'toggle_icon_list', 'responsive_toggle_icon'];
    foreach ($fileFields as $field) {
      $oldValue = $config->get($field);
      $newValue = $form_state->getValue($field);
      
      if (!empty($oldValue) && $oldValue != $newValue) {
        if ($oldFile = File::load(current($oldValue))) {
          $oldFile->delete();
        }
      }
      if (!empty($newValue) && $oldValue != $newValue) {
        $file = File::load(current($newValue));
        $file->setPermanent();
        $file->save();
        $file_usage = \Drupal::service('file.usage');
        $file_usage->add($file, 'workbc_career_trek', 'managed_file', current($newValue));
  
      }
    }

    // Save all form values
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
    ];
    foreach ($fields as $formKey => $configKey) {
      $config->set($formKey, $form_state->getValue($configKey));
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
