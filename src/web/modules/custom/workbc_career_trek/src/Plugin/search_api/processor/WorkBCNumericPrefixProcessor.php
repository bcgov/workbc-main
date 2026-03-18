<?php

namespace Drupal\workbc_career_trek\Plugin\search_api\processor;

use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Query\QueryInterface;

/**
 * Ensures numeric search terms only match the beginning of indexed content.
 *
 * @SearchApiProcessor(
 *   id = "workbc_numeric_prefix",
 *   label = @Translation("WorkBC: Numeric Starts With Filter"),
 *   description = @Translation("Only matches numeric terms at the start of fields."),
 *   stages = {
 *     "preprocess_query" = 0
 *   }
 * )
 */
class WorkBCNumericPrefixProcessor extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    $keys = $query->getKeys();

    // Only proceed if keys are an array of parsed tokens.
    if (!is_array($keys)) {
      return;
    }

    foreach ($keys as $key) {
      if (is_array($key) && !empty($key['#full_numeric_prefix']) && isset($key['value'])) {
        // Add a condition that only matches when the field starts with the numeric value.
        $query->addCondition("career_noc", $key['value'], 'STARTS_WITH', ['operator' => 'OR']);

      }
    }
  }

}
