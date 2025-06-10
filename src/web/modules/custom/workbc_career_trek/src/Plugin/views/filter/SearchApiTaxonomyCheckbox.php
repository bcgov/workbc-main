<?php

namespace Drupal\workbc_career_trek\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\StringFilter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

/**
 * Exposed filter: Convert textfield to multiple checkboxes for taxonomy terms.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("views_select_filter_string")
 */

class SearchApiTaxonomyCheckbox extends StringFilter
{

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Add a field to let the user specify the vocabulary machine name.
    $form['taxonomy_vid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Taxonomy Vocabulary (machine name)'),
      '#default_value' => !empty($this->options['taxonomy_vid']) ? $this->options['taxonomy_vid'] : '',
      '#description' => $this->t('Enter the machine name of the taxonomy vocabulary to use for this filter.'),
      '#required' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    $values = $form_state->getValues();
    if (!empty($values['options']['taxonomy_vid'])) {
      $this->options['taxonomy_vid'] = $values['options']['taxonomy_vid'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    // Only show checkboxes if exposed and vocabulary is set.
    $exposed = $form_state->get('exposed');
    $vid = !empty($this->options['taxonomy_vid']) ? $this->options['taxonomy_vid'] : '';
    unset($form['value']);
    if ($exposed !== TRUE || empty($vid)) {
      parent::valueForm($form, $form_state);
      return;
    }

    // Load taxonomy terms for the given vocabulary.
    $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree($vid);

    $options = [];
    foreach ($terms as $term) {
      $options[$term->tid] = $term->name;
    }

    if (empty($options)) {
      $options = ['' => $this->t('No terms found for vocabulary: @vid', ['@vid' => $vid])];
    }

    $form['value'] = [
      '#type' => 'select',
      '#title' => $this->t('Select terms'),
      '#options' => $options,
      '#multiple' => TRUE,
      '#default_value' => is_array($this->value) ? $this->value : [],
      '#validated' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Only alter the query if values are selected.
    if (empty($this->value) || (is_array($this->value) && count(array_filter($this->value)) === 0)) {
      return;
    }

    // Remove unchecked (0) values.
    $tids = array_filter($this->value);

    // The field name in the index may be different; adjust as needed.
    // This assumes the field is stored as a string of term IDs or as a reference.
    // For Search API, you may need to adjust the field name.
    $field = $this->realField;

    // Add the filter for the selected taxonomy term IDs.
    $this->query->addWhere($this->options['group'], $field, $tids, 'IN');
  }
}