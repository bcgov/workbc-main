<?php

    namespace Drupal\workbc_custom\Form;

    use Drupal\Core\Form\ConfigFormBase;
    use Drupal\Core\Form\FormStateInterface;
    use Drupal\Core\Link;

    /**
     * Class AdminSettingsForm.
     *
     * @package Drupal\custom_mailing\Form
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

        $form['pathsettings'] = [
          '#tree' => TRUE,
          '#type' => 'details',
          '#title' => $this->t('Path settings'),
          '#open' => TRUE,
        ];

        $form['pathsettings']['order_form'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Publications Order Form path'),
          '#default_value' => $config->get('pathsettings.order_form'),
        ];

        $form['collectionsettings'] = [
          '#tree' => TRUE,
          '#type' => 'details',
          '#title' => $this->t('Collection Notice settings'),
          '#open' => TRUE,
        ];

        $text = $config->get('collectionsettings.notice');
        if (is_null($text) || !isset($text['value'])) {
          $text['value'] = "";
          $text['format'] = "basic_html";
        }

        $form['collectionsettings']['notice'] = [
          '#type' => 'text_format',
          '#title' => $this->t('Collection Notice text'),
          '#default_value' => $text['value'],
          '#format' => $text['format'],
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
        $config->set('pathsettings', $form_state->getValue('pathsettings'));
        $config->set('collectionsettings', $form_state->getValue('collectionsettings'));
        $config->save();
        parent::submitForm($form, $form_state);
      }

    }
