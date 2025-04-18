<?php

namespace Drupal\workbc_career_trek\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\processor\Property\CustomValueProperty;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Adds region data from a custom API to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "regions_processor",
 *   label = @Translation("Regions Processor"),
 *   description = @Translation("Pulls region data from an external API and indexes it."),
 *   stages = {
 *     "add_properties" = 0,
 *     "preprocess_index" = -10
 *   }
 * )
 */
class RegionsProcessor extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Region API Field'),
        'description' => $this->t('A region field fetched from the Career Trek API.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['region_api_field'] = new CustomValueProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'region_api_field');

    $entity = $item->getOriginalObject()->getValue();
    if ($entity instanceof EntityInterface && $entity->hasField('field_noc')) {
      $identifier = $entity->get('field_noc')->value;

      if ($identifier) {
        $api_data = $this->fetchRegionDataFromApi($identifier);
        if (!empty($api_data)) {
          foreach ($fields as $field) {
            $field->addValue((string) $api_data);
          }
        }
      }
    }
  }

  /**
   * Call your custom API to fetch region data.
   *
   * @param string $id
   *   The identifier (e.g., NOC code).
   *
   * @return string
   *   The region string or empty string if not found.
   */
  protected function fetchRegionDataFromApi($id) {
    try {
      $ssot = ssotCareerProfile($id);
      if (!empty($ssot['career_trek'][0])) {
        return $ssot['career_trek'][0]['region'] ?? '';
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('workbc_career_trek')->error('API error: @message', ['@message' => $e->getMessage()]);
    }

    return '';
  }

}
