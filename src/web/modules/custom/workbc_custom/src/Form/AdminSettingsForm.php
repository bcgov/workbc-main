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

        $files = getUnmanagedFiles();
        $form['reports']['files'] = [
          '#tree' => TRUE,
          '#type' => 'details',
          '#title' => $this->t('Unmanaged files'),
          '#description' => $this->t('
            This is a report of pages containing unmanaged files instead of media library items.<br>
            Click the <b>Edit</b> link to edit the page, then look for the field named in the <b>Field</b> column to find the content to be edited.<br>
            Click the <b>Source</b> button of the editor to locate the unmanaged file(s) in the field content.'
          ),
          '#open' => FALSE,
        ];

        $form['reports']['files']['table'] = [
          '#theme' => 'table',
          '#header' => ['Page', 'Field', 'Edit'],
          '#rows' => array_map(function ($file) {
            return [
              $file['title'],
              $file['label'],
              Link::fromTextAndUrl($this->t('Edit'), $file['edit_url'])
            ];
          }, $files),
        ];

        $form['environment'] = [
          '#tree' => TRUE,
          '#type' => 'details',
          '#title' => $this->t('Environment'),
          '#open' => FALSE,
        ];

        ob_start();
        phpinfo((INFO_VARIABLES | INFO_ENVIRONMENT));
        $env = ob_get_clean();
        $form['environment']['env'] = [
          '#type' => 'markup',
          '#markup' => $env,
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
