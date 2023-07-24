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

    if (!isset($values->high_opportunity_occupations_wage_rate_median) ||
        $values->high_opportunity_occupations_wage_rate_median == 0
    ) {
      if (!isset($values->high_opportunity_occupations_annual_salary_median) ||
          $values->high_opportunity_occupations_annual_salary_median == 0
      ) {
        $wage = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      }
      else {
        $options = array(
          'decimals' => 0,
          'prefix' => "$",
          'suffix' => "*",
          'na_if_empty' => TRUE,         
        );          
        $wage = ssotFormatNumber($values->high_opportunity_occupations_annual_salary_median, $options);
      }
    }
    else if ($values->high_opportunity_occupations_annual_salary_median == $values->high_opportunity_occupations_wage_rate_median) {
      $options = array(
        'decimals' => 0,
        'prefix' => "$",
        'suffix' => "*",
        'na_if_empty' => TRUE,
      );       
      $wage = ssotFormatNumber($values->high_opportunity_occupations_annual_salary_median, $options);
    }
    else {
      $options = array(
        'decimals' => 2,
        'prefix' => "$",
        'na_if_empty' => TRUE,
      );      
      $wage = ssotFormatNumber($values->high_opportunity_occupations_wage_rate_median, $options);
    }
    return $wage;

  }

}