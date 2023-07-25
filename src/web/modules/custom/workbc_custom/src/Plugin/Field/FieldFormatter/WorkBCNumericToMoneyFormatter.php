<?php

namespace Drupal\workbc_custom\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Plugin implementation of the 'workbc_numeric_to_money' formatter.
 *
 * @FieldFormatter(
 *   id = "workbc_numeric_to_money",
 *   label = @Translation("Numeric to Money"),
 *   field_types = {
 *     "integer",
 *     "numeric",
 *   }
 * )
 */
class WorkBCNumericToMoneyFormatter extends FormatterBase {


  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Displays converts numeric to money format');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $settings = $this->getSettings();

    $options = array(
      'decimals' => $this->getSetting('decimals'),
      'prefix' => $this->getSetting('prefix'),
    );
    foreach ($items as $delta => $item) {
      $result = ssotFormatNumber($item->value, $options);
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
        '#title' => t('Prefix'),
        '#default_value' => $this->getSetting('prefix'),
        '#description' => t('Text to put before the number, such as currency symbol.'),
      ];
      $elements['decimals'] = [
        '#type' => 'textfield',
        '#title' => t('Decimals'),
        '#default_value' => $this->getSetting('decimals'),
      ];
    return $elements;
  }

}
