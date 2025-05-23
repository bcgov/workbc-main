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
 *   id = "occupational_category_processor",
 *   label = @Translation("Occupational Category Processor"),
 *   description = @Translation("Pulls occupational category data from an external API and indexes it."),
 *   stages = {
 *     "add_properties" = 0,
 *     "preprocess_index" = -10
 *   }
 * )
 */
class OccupationalCategoryProcessor extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Occupational Category Field'),
        'description' => $this->t('An occupational category field fetched from the Career Trek API.'),
        'type' => 'string',
        'is_list' => TRUE, // Mark as multi-value
        'processor_id' => $this->getPluginId(),
      ];
      $properties['occupational_category_api_field'] = new CustomValueProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'occupational_category_api_field');

    $entity = $item->getOriginalObject()->getValue();
    if ($entity instanceof EntityInterface && $entity->hasField('field_noc')) {
      $identifier = $entity->get('field_noc')->value;

      if ($identifier) {
        $api_data = $this->fetchOccupationalCategoryDataFromApi($identifier);
        if (!empty($api_data) && is_array($api_data)) {
          foreach ($fields as $field) {
            foreach ($api_data as $category) {
              $field->addValue((string) $category);
            }
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
   *   An array of occupational category strings, or empty array if not found.
   */
  protected function fetchOccupationalCategoryDataFromApi($id) {
    $categories = [];
    try {
      $ssot = ssotFullCareerProfile($id);
      if (!empty($ssot['occupational_category']) && is_array($ssot['occupational_category'])) {
        foreach ($ssot['occupational_category'] as $cat) {
          if (!empty($cat['category'])) {
            $categories[] = $cat['category'];
          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('workbc_career_trek')->error('API error: @message', ['@message' => $e->getMessage()]);
    }

    return $categories;
  }

}
