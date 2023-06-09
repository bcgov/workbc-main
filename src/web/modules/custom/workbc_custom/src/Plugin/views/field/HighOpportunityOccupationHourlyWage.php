<?php

namespace Drupal\workbc_custom\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Url;

/**
 * Field handler to flag the node type.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("high_opportunity_occupations_hourly_wage")
 */
class HighOpportunityOccupationHourlyWage extends FieldPluginBase {

  /**
   * @{inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {

    $wage = "$" . number_format($values->high_opportunity_occupations_wage_rate_median,2);
    return $wage;

  }

}