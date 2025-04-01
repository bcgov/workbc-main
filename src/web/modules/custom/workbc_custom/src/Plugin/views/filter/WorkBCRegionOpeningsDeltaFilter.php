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
 * @ViewsFilter("workbc_node_region_delta_filter")
 */
class WorkBCRegionOpeningsDeltaFilter extends FilterPluginBase {

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
    $form['delta'] = [
      '#type' => 'select',
      '#options' => $this->getRegionOptions(),
      '#default_value' => '0',
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    if ($this->options['exposed']) {
      $value = $this->value[0];
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

    /**
   * It generates all the regions that will be selectable.
   *
   * @param bool $emptyOption
   *   Add (or not) the empty option.
   *
   * @return array
   *   Array with all regions.
   */
  private function getRegionOptions($emptyOption = FALSE) {

		$regions = [];
		$regions[0] = "British Columbia";
		$regions[1] = "Cariboo";
		$regions[2] = "Kootenay";
		$regions[3] = "Mainland/Southwest";
		$regions[4] = "North Coast and Nechako";
		$regions[5] = "Northeast";
		$regions[6] = "Thompson-Okanagan";
		$regions[7] = "Vancouver Island/Coast";

    return $regions;
  }

}
