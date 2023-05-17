<?php

namespace Drupal\gdx_analytics_drupal_snowplow\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SettingsForm.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'gdx_analytics_drupal_snowplow.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gdx_analytics_drupal_snowplow_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('gdx_analytics_drupal_snowplow.settings');
    
    $form['gdx_collector_mode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter Collector Environment'),
      '#default_value' => $config->get('gdx_collector_mode'),
      '#description' => $this->t('Enter the value for the Snowplow endpoint as provided to you. Do not include "https://" or "http://"'),
      '#maxlength' => 128,
      '#size' => 60,
      '#required' => true,
    ]; 
    $form['gdx_analytics_snowplow_script_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter Snowplow tracking script URI'),
      '#default_value' => $config->get('gdx_analytics_snowplow_script_uri'),
      '#description' => $this->t('Enter the URL of the Snowplow Library as provided to you. This should be a full URL including "https://" or "http://"'),
      '#maxlength' => 256,
      '#size' => 60,
      '#required' => true,
    ];
    $form['gdx_analytics_app_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter App ID'),
      '#default_value' => $config->get('gdx_analytics_app_id'),
      '#description' => $this->t('Enter the value of the App ID for your site as provided to you.'),
      '#maxlength' => 256,
      '#size' => 60,
      '#required' => true,
    ];
    $form['gdx_analytics_snowplow_version'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Snowplow Search on/off'),
      '#default_value' => $config->get('gdx_analytics_snowplow_version'),
      '#description' => $this->t('If checked, Snowplow will track searches made using the Drupal search module.'),
      '#size' => 60,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('gdx_analytics_drupal_snowplow.settings')
      ->set('gdx_collector_mode', $form_state->getValue('gdx_collector_mode'))
      ->set('gdx_analytics_snowplow_version', $form_state->getValue('gdx_analytics_snowplow_version'))
      ->set('gdx_analytics_snowplow_script_uri', $form_state->getValue('gdx_analytics_snowplow_script_uri'))
      ->set('gdx_analytics_app_id', $form_state->getValue('gdx_analytics_app_id'))
      ->save();
  }

}
