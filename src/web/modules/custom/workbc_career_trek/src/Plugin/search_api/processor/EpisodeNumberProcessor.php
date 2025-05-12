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
 *   id = "episode_number_processor",
 *   label = @Translation("Episode Number Processor"),
 *   description = @Translation("Pulls Episode Number data from an external API and indexes it."),
 *   stages = {
 *     "add_properties" = 0,
 *     "preprocess_index" = -10
 *   }
 * )
 */
class EpisodeNumberProcessor extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Episode Number'),
        'description' => $this->t('An episode number field fetched from the Career Trek API.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['episode_number'] = new CustomValueProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'episode_number');

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
   * @return string
   *   The occupational category string or empty string if not found.
   */
  protected function fetchDataFromApi($id) {
    try {
      $ssot = ssotFullCareerProfile($id);
      if (!empty($ssot['career_trek'][0])) {
        return $ssot['career_trek'][0]['episode_num'] ?? '';
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('workbc_career_trek')->error('API error: @message', ['@message' => $e->getMessage()]);
    }

    return '';
  }

}
