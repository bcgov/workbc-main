<?php

namespace Drupal\workbc_custom\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'options_select' widget.
 *
 * @FieldWidget(
 *   id = "workbc_select_epbc_category",
 *   label = @Translation("Select list (EPBC Category Parent Terms)"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class EPBCCategorySelectWidget extends \Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $vid = 'epbc_categories';
    $tree = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($vid, 0, 1, TRUE);

    $options = [];

    $options['_none'] = '- Explore Your Career Options -';
    foreach ($tree as $key => $category) {
      $options[$category->tid->value] = $category->name->value;
    }

    $element += [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $this->getSelectedOptions($items),
      '#multiple' => $this->multiple && count($this->options) > 1,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function sanitizeLabel(&$label) {
    // Select form inputs allow unencoded HTML entities, but no HTML tags.
    $label = Html::decodeEntities(strip_tags($label));
  }

  /**
   * {@inheritdoc}
   */
  protected function supportsGroups() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmptyLabel() {
    if ($this->multiple) {
      // Multiple select: add a 'none' option for non-required fields.
      if (!$this->required) {
        return t('- None -');
      }
    }
    else {
      // Single select: add a 'none' option for non-required fields,
      // and a 'select a value' option for required fields that do not come
      // with a value selected.
      if (!$this->required) {
        return t('- None -');
      }
      if (!$this->has_value) {
        return t('- Select a value -');
      }
    }
  }

}
