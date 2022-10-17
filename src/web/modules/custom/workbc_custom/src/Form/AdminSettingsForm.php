<?php

    namespace Drupal\workbc_custom\Form;

    use Drupal\Core\Form\ConfigFormBase;
    use Drupal\Core\Form\FormStateInterface;

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
          '#type' => 'fieldset',
          '#title' => $this->t('Path settings'),
        ];

        $form['pathsettings']['order_form'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Publications Order Form path.'),
          '#default_value' => $config->get('pathsettings.order_form'),
        ];

        return parent::buildForm($form, $form_state);
      }

      /**
       * {@inheritdoc}
       */
      public function submitForm(array &$form, FormStateInterface $form_state) {
        $config = $this->config('workbc_custom.settings');
        $config->set('pathsettings', $form_state->getValue('pathsettings'));
        $config->save();
        parent::submitForm($form, $form_state);
      }

    }
