<?php

/**
 * @file
 * Definition of Drupal\workbc_extra_fields\Plugin\views\field\IndustryProfileOverviewSource
 */

namespace Drupal\workbc_extra_fields\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to flag the node type.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("industry_profile_overview_source")
 */
class IndustryProfileOverviewSource extends FieldPluginBase {

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
    $node = $this->getEntity($values);
    if ($node->bundle() == "industry_profile") {
      if (!empty($values->ssot_data) && isset($values->ssot_data['sources']['labour_force_survey_industry'])) {
        $output = $values->ssot_data['sources']['labour_force_survey_industry']['label'];;
      }
      else {
        $output = WORKBC_EXTRA_FIELDS_NOT_AVAILABLE;
      }
      return $output;
    }
    else {
      return $this->t('n/a');
    }
  }
}
