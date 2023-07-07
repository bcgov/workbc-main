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
 * @ViewsField("high_opportunity_occupations_career_link")
 */
class HighOpportunityOccupationCareerProfileLink extends FieldPluginBase {

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
    static $firstTime = true;

    if ($firstTime) {
      foreach ($values as $key => $value) {
        $display = is_string($value) ? " - Value: " . $value : "";
        \Drupal::logger('workbc_custom')->notice("Key: " . $key . $display);
      }
    }
    if (isset($values->high_opportunity_occupations_noc)) {
      if ($firstTime) {
        \Drupal::logger('workbc_custom')->notice("NOC: [" . $values->high_opportunity_occupations_noc . "]");
      }
      $query = \Drupal::database()->select('node__field_noc', 'n');
      $query->addField('n', 'entity_id');
      $query->condition('n.field_noc_value', $values->high_opportunity_occupations_noc);
      $results = $query->execute()->fetchAssoc();
      if(!empty($results['entity_id'])) {
        $nid = $results['entity_id'];
        $link = Url::fromUri('internal:/node/'.$nid)->toString();
        if ($firstTime) {
          \Drupal::logger('workbc_custom')->notice("Career Profile found - " . $link);
        }
      } else {
        $link = "";
        if ($firstTime) {
          \Drupal::logger('workbc_custom')->notice("No Career Profile found");
        }
      }
    }
    else {
      $link = "";
      if ($firstTime) {      
        \Drupal::logger('workbc_custom')->notice("HOO NOC field has no value");      
      }
    }
    if ($firstTime) {
      $firstTime = false;
    }
    return $link;
  }



}