<?php

namespace Drupal\workbc_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
* Class AdminSettingsForm.
*
* @package Drupal\workbc_custom\Form
*/
class AdminSettingsForm extends ConfigFormBase {

  /**
  * {@inheritdoc}
  */
  public function getFormId() {
    return 'workbc_custom_admin_settings_form';
  }

  /**
  * {@inheritdoc}
  */
  protected function getEditableConfigNames() {
    return [
      'workbc_custom.settings',
    ];
  }

  /**
  * {@inheritdoc}
  */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('workbc_custom.settings');

    $form['show_feedback'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show GDX feedback component'),
      '#default_value' => $config->get('show_feedback'),
    ];

    $form['collectionsettings'] = [
      '#tree' => TRUE,
      '#type' => 'details',
      '#title' => $this->t('Collection notice'),
      '#open' => TRUE,
    ];

    $notice = $config->get('collectionsettings.notice');
    if (is_null($notice) || !isset($notice['value'])) {
      $notice['value'] = '';
      $notice['format'] = 'basic_html';
    }
    $form['collectionsettings']['notice'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Notice text'),
      '#default_value' => $notice['value'],
      '#format' => $notice['format'],
    ];

    $form['reports'] = [
      '#tree' => TRUE,
      '#type' => 'details',
      '#title' => $this->t('Reports'),
      '#open' => TRUE,
    ];

    $form['reports']['notice'] = [
      '#markup' => 'Moved to the <a href="/admin/reports">reports section</a>. Check for <b>WorkBC</b> reports there.'
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
  * {@inheritdoc}
  */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('workbc_custom.settings');
    $config->set('show_feedback', $form_state->getValue('show_feedback'));
    $config->set('collectionsettings', $form_state->getValue('collectionsettings'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
