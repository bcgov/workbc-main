<?php

namespace Drupal\workbc_career_trek\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\processor\Property\CustomValueProperty;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Adds occupational category data from a custom API to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "annual_salary_processor",
 *   label = @Translation("Annual Salary Processor"),
 *   description = @Translation("Pulls Annual Salary data from an external API and indexes it."),
 *   stages = {
 *     "add_properties" = 0,
 *     "preprocess_index" = -10
 *   }
 * )
 */
class AnnualSalaryProcessor extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Annual Salary'),
        'description' => $this->t('A Annual Salary field fetched from the Career Trek API.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['annual_salary'] = new CustomValueProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'annual_salary');

    $entity = $item->getOriginalObject()->getValue();
    if ($entity instanceof EntityInterface && $entity->hasField('field_noc')) {
      $identifier = $entity->get('field_noc')->value;

      if ($identifier) {
        $api_data = $this->fetchDataFromApi($identifier);
        if (!empty($api_data)) {
          foreach ($fields as $field) {
            $field->addValue((string) $api_data);
          }
        }
      }
    }
  }

  /**
   * Call your custom API to fetch occupational category data.
   *
   * @param string $id
   *   The identifier (e.g., NOC code).
   *
   * @return array
   *   The skills array or empty array if not found.
   */
  protected function fetchDataFromApi($id) {
    try {
      $ssot = ssotFullCareerProfile($id);
      if (!empty($ssot['wages']['calculated_median_annual_salary'])) {
        return $ssot['wages']['calculated_median_annual_salary'] ?? '';
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('workbc_career_trek')->error('API error: @message', ['@message' => $e->getMessage()]);
    }

    return [];
  }

}
