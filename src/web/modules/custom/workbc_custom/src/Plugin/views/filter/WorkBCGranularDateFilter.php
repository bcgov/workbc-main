<?php

namespace Drupal\workbc_custom\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;

/**
 * Filters by given list of node title options.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("workbc_node_granular_date_filter")
 */
class WorkBCGranularDateFilter extends FilterPluginBase {

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
    $form['value'] = !empty($form['value']) ? $form['value'] : [];
    parent::buildExposedForm($form, $form_state);
    $filter_id = $this->getFilterId();
    // Field which really filters.
    $form[$filter_id] = [
      '#type' => 'hidden',
      '#value' => '',
    ];

    // Auxiliary fields.
    $form['exposed_year'] = [
      '#type' => 'select',
      '#options' => $this->getYearsOptions(TRUE),
      '#default_value' => 'All',
      '#attributes' => [
        'name' => 'exposed_year',
      ],
    ];

    $form['exposed_month'] = [
      '#type' => 'select',
      '#options' => $this->getMonthsOptions(TRUE),
      '#default_value' => 'All',
      '#states' => array(
        'disabled' => array(
          ':input[name="exposed_year"]' => array('value' => 'All'),
        ),
      ),
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }
    // Given exposed year and month, set the value of the field which really filters.
    $input[$this->options['expose']['identifier']] = $input['exposed_year'] . '-' . $input['exposed_month'];

    $rc = parent::acceptExposedInput($input);

    return $rc;
  }

  /**
   * This method returns the ID of the fake field which contains this plugin.
   *
   * It is important to put this ID to the exposed field of this plugin for the following reasons:
   * a) To avoid problems with FilterPluginBase::acceptExposedInput function
   * b) To allow this filter to be printed on twig templates with {{ form.nodes_granular_dates }}
   *
   * @return string
   *   ID of the field which contains this plugin.
   */
  private function getFilterId() {
    return $this->options['expose']['identifier'];
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
  private function getYearsOptions($emptyOption = FALSE) {
    $return = [];

    if ($emptyOption) {
      $return['All'] = $this->t('Year');
    }

    $anys = range($this->options['granular_year_from'], date("Y"));

    foreach ($anys as $year) {
      $return[$year] = $year;
    }

    return $return;
  }

  /**
   * It generates all the months that will be selectable.
   *
   * @param bool $emptyOption
   *   Add (or not) the empty option.
   *
   * @return array
   *   Array with all months.
   */
  private function getMonthsOptions($emptyOption = FALSE) {
    $return = [];

		$months = [];
		$months[1] = "January";
		$months[2] = "February";
		$months[3] = "March";
		$months[4] = "April";
		$months[5] = "May";
		$months[6] = "June";
		$months[7] = "July";
		$months[8] = "August";
		$months[9] = "September";
		$months[10] = "October";
		$months[11] = "November";
		$months[12] = "December";

    if ($emptyOption) {
      $return['All'] = $this->t('Month');
    }

    $mesos = range(1, 12);

    foreach ($mesos as $m) {
      $return[$m] = $months[$m];
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    if (!$this->options['exposed']) {
      // Administrative value.
      $this->queryFilter($this->options['granular_field_name'], $this->options['node_year'] . '-' . $this->options['node_month']);
    }
    else {
      // Exposed value.
      if (empty($this->value) || empty($this->value[0])) {
        return;
      }

      $this->queryFilter($this->options['granular_field_name'], $this->value[0]);
    }
  }

  /**
   * Add a zero to the left if needed.
   *
   * @param int $month
   *   Month
   *
   * @return mixed
   *   Month with two ciphers.
   */
  private function formatMonth($month) {
    return ($month < 10) ? '0' . $month : $month;
  }

  /**
   * Filters by given year and month.
   *
   * @param $fieldName
   *   Machine name of the field.
   * @param $anyMes
   *   Year and month selected.
   */
  private function queryFilter($fieldName, $anyMes) {
    $anyMes = $this->securityFilter($anyMes);

    $array_date = explode("-", $anyMes);
    $year = $array_date[0];
    $month = $array_date[1];

    if ($year == '') {
      $year = 'All';
    }
    if ($month == '') {
      $month = 'All';
    }

    if ($year != 'All' && $month != 'All') {
      $next_month = $month + 1;
      $prev_year =  $year;
      if ($next_month == 13) {
        $next_month = 1;
        $year++;
      }

      $next_month = $this->formatMonth($next_month);

      $first = $prev_year . '-' . $this->formatMonth($month) . '-01T00:00:00';
      $last = $year . '-' . $next_month . '-01T00:00:00';

      if ($fieldName == 'created' || $fieldName == 'changed' || $fieldName == 'published_date' || $fieldName == 'corrected_date') {
        $firstTime = strtotime($first);
        $lastTime = strtotime($last);
        $this->query->addTable("node__field_data");
        $this->query->addWhere("AND", "node_field_data.{$fieldName}", $firstTime, ">=");
        $this->query->addWhere("AND", "node_field_data.{$fieldName}", $lastTime, "<");
      }
      else {
        $this->query->addTable("node__{$fieldName}");
        $this->query->addWhere("AND", "node__{$fieldName}.{$fieldName}_value", $first, ">=");
        $this->query->addWhere("AND", "node__{$fieldName}.{$fieldName}_value", $last, "<");
      }
    }
    elseif ($year != 'All' && $month == 'All') {
      $next_year = $year + 1;

      $first = $year . '-01-01T00:00:00';
      $last = $next_year . '-01-01T00:00:00';

      if ($fieldName == 'created' || $fieldName == 'changed' || $fieldName == 'published_date' || $fieldName == 'corrected_date') {
        $firstTime = strtotime($first);
        $lastTime = strtotime($last);
        $this->query->addTable("node__field_data");
        $this->query->addWhere("AND", "node_field_data.{$fieldName}", $firstTime, ">=");
        $this->query->addWhere("AND", "node_field_data.{$fieldName}", $lastTime, "<");
      }
      else {
        $this->query->addTable("node__{$fieldName}");
        $this->query->addWhere("AND", "node__{$fieldName}.{$fieldName}_value", $first, ">=");
        $this->query->addWhere("AND", "node__{$fieldName}.{$fieldName}_value", $last, "<");
      }
    }
    elseif ($year == 'All' && $month != 'All') {
      $monthWithZero = $this->formatMonth($month);

      if ($fieldName == 'created' || $fieldName == 'changed' || $fieldName == 'published_date' || $fieldName == 'corrected_date') {
        $this->query->addWhereExpression(0, "from_unixtime(node_field_data.{$fieldName}, '%m') = {$monthWithZero}", []);
      }
      else {
        $this->query->addTable("node__{$fieldName}");
        $this->query->addWhereExpression(0, "date_format(node__{$fieldName}.{$fieldName}_value, '%m') = {$monthWithZero}", []);
      }
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

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    if (!$this->options['exposed']) {
      $form['node_year'] = [
        '#type' => 'select',
        '#title' => $this->t('Year'),
        '#options' => $this->getYearsOptions(TRUE),
        '#default_value' => isset($this->options['node_year']) ? $this->options['node_year'] : NULL,
      ];

      $form['node_month'] = [
        '#type' => 'select',
        '#title' => $this->t('Month'),
        '#options' => $this->getMonthsOptions(TRUE),
        '#default_value' => isset($this->options['node_year']) ? $this->options['node_year'] : NULL,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['node_year'] = ['default' => ''];
    $options['node_month'] = ['default' => ''];
    $options['granular_field_name'] = ['default' => ''];
    $options['granular_year_from'] = ['default' => 2000];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['granular_field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Use Node Granular Date filter with this field name (enter machine name)'),
      '#description' => $this->t('Machine field names appear on content types field list (e.g. field_fecha_blog). You can also use "created" and "changed" properties.'),
      '#default_value' => isset($this->options['granular_field_name']) ? $this->options['granular_field_name'] : NULL,
      '#required' => TRUE,
    ];

    $form['granular_year_from'] = array(
      '#type' => 'number',
      '#title' => $this->t('Year from'),
      '#description' => $this->t('Customize the year select range'),
      '#default_value' => 2000,
      '#required' => TRUE,
    );

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    // Exposed filter.
    if ($this->options['exposed']) {
      $variables = [
        '@field' => $this->options['granular_field_name'],
      ];
      return $this->t('Exposed on field "@field"', $variables);
    }

    // Administrative filter.
    $variables = [
      '@year' => $this->options['node_year'],
      '@month' => $this->options['node_month'],
      '@field' => $this->options['granular_field_name'],
    ];
    return $this->t('Filter on field "@field" [@year (year) - @month (month)] ', $variables);
  }

}
