<?php

namespace Drupal\workbc_custom\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Plugin implementation of the 'workbc_file_custom_link_text' formatter.
 *
 * @FieldFormatter(
 *   id = "workbc_file_custom_link_text",
 *   label = @Translation("Custom link text"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class WorkBCFileCustomLinkTextFormatter extends FormatterBase {


  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Displays custom link text ');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $settings = $this->getSettings();

    foreach ($items as $delta => $item) {
      $file = \Drupal\file\Entity\File::load($item->get('target_id')->getCastedValue());
      $options = [];
      if (!empty($settings['target'])) {
        $options['attributes']['target'] = $settings['target'];
      }
      $result = Link::fromTextAndUrl(t($settings['custom_text']), Url::fromUri('internal:'.$file->createFileUrl(), $options))->toString();
      $elements[$delta] = ['#markup' => $result];
    }
    return $elements;
  }


  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      // Declare a setting named 'custom_text', with
      // a default value of 'short'
      'custom_text' => '',
      'target' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

      $elements = parent::settingsForm($form, $form_state);

      $elements['custom_text'] = [
        '#type' => 'textfield',
        '#title' => t('Custom link text'),
        '#default_value' => $this->getSetting('custom_text'),
        '#description' => t('Enter text to be displayed.'),
      ];
      $elements['target'] = [
        '#type' => 'checkbox',
        '#title' => t('Open link in new window'),
        '#return_value' => '_blank',
        '#default_value' => $this->getSetting('target'),
      ];
    return $elements;
  }

}
