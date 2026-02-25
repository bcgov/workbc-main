<?php

namespace Drupal\workbc_custom\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'workbc_career_categories_section' formatter.
 *
 * @FieldFormatter(
 *   id = "workbc_career_categories_section",
 *   label = @Translation("Career Categories Section"),
 *   field_types = {
 *     "entity_reference_revisions",
 *   }
 * )
 */
class WorkBCSectionCareerCategoryGridFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $elements = [];
    $elements[] = \Drupal::formBuilder()->getForm('Drupal\workbc_custom\Form\CareerCategoryGridForm', $items);

    return $elements;
  }
}
