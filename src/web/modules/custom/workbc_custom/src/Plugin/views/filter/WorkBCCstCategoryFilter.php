<?php

namespace Drupal\workbc_custom\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\taxonomy\VocabularyStorageInterface;

/**
 * Filters by given list of node title options.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("workbc_node_cst_category_filter")
 */
class WorkBCCstCategoryFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function canBuildGroup() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {
    parent::buildExposedForm($form, $form_state);

    // Auxiliary fields.
    $form['field_cst_categories_target_id'] = [
      '#type' => 'select',
      '#options' => $this->getCstCategoryOptions(),
      '#default_value' => 'All',
    ];

  }


  /**
   * It generates all the years that will be selectable.
   *
   * @param bool $emptyOption
   *   Add (or not) the empty option.
   *
   * @return array
   *   Array with all years.
   */
  private function getCstCategoryOptions() {
    $return = [];

    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('cst_categories', 0, 1, TRUE);

    foreach ($terms as $term) {
      $return[$term->id()] = $term->getName();
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    if ($this->options['exposed']) {
      if (empty($this->value) || empty($this->value[0])) {
        return;
      }

      $param = \Drupal::request()->query->all();
      $delta = !empty($param['delta']) ? $param['delta'] : 0;
      $tids = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('vid', 'cst_categories')
        ->condition('parent', $this->value[0])
        ->condition('field_region', $delta)
        ->execute();

      $value = array_values($tids)[0];
      $table = $this->options['table'];
      $query = $this->query;
      $query->addWhere($this->options['group'], $table . '.' . $this->options['field'], $value, '=');
    }
  }


  /**
   * Security filter.
   *
   * @param mixed $value
   *   Input.
   *
   * @return mixed
   *   Sanitized value of input.
   */
  private function securityFilter($value) {
    $value = Html::escape($value);
    $value = Xss::filter($value);
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    if (!empty($this->value)) {
      parent::validate();
    }
  }

  public function adminSummary() {

    // Exposed filter.
    if ($this->options['exposed']) {
      return $this->t('exposed');
    }
    else {
      return "";
    }
  }

}
