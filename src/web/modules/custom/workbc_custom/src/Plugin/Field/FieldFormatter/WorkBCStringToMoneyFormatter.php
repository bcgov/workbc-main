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
 *   id = "workbc_string_to_money",
 *   label = @Translation("String to Money"),
 *   field_types = {
 *     "numeric",
 *   }
 * )
 */
class WorkBCStringToMoneyFormatter extends FormatterBase {


  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Displays converts string to money format');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $settings = $this->getSettings();

    foreach ($items as $delta => $item) {
// ksm($item);
      $result = "$00.00";
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
      'prefix' => '',
      'decimals' => '2',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

      $elements = parent::settingsForm($form, $form_state);

      $elements['prefix'] = [
        '#type' => 'textfield',
        '#title' => t('Custom link text'),
        '#default_value' => $this->getSetting('prefix'),
        '#description' => t('Enter prefix.'),
      ];
      $elements['decimals'] = [
        '#type' => 'textfield',
        '#title' => t('Decimals'),
        '#return_value' => '2',
        '#default_value' => $this->getSetting('decimals'),
      ];
    return $elements;
  }

}
