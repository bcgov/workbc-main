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
 *   id = "skills_processor",
 *   label = @Translation("Skills Processor"),
 *   description = @Translation("Pulls Skills data from an external API and indexes it."),
 *   stages = {
 *     "add_properties" = 0,
 *     "preprocess_index" = -10
 *   }
 * )
 */
class SkillsProcessor extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Skills'),
        'description' => $this->t('A Skills field fetched from the Career Trek API.'),
        'type' => 'string',
        'is_list' => TRUE, // Indicate this field stores multiple values.
        'processor_id' => $this->getPluginId(),
      ];
      $properties['skills'] = new CustomValueProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'skills');

    $entity = $item->getOriginalObject()->getValue();
    if ($entity instanceof EntityInterface && $entity->hasField('field_noc')) {
      $identifier = $entity->get('field_noc')->value;

      if ($identifier) {
        $api_data = $this->fetchDataFromApi($identifier);
        if (!empty($api_data) && is_array($api_data)) {
          foreach ($fields as $field) {
            foreach ($api_data as $skill) {
              $field->addValue((string) $skill);
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
   *   The skills array or empty array if not found.
   */
  protected function fetchDataFromApi($id) {
    try {
      $ssot = ssotFullCareerProfile($id);
      if (!empty($ssot['skills'][0])) {
        // Sort skills by importance descending
        usort($ssot['skills'], function($a, $b) {
          return $b['importance'] <=> $a['importance'];
        });

        $skills = [];
        $count = 0;
        foreach($ssot['skills'] as $skill) {
          if ($count >= 6) {
            break;
          }
          $term_name = $skill['skills_competencies'];
          $term = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties(['name' => $term_name, 'vid' => 'skills']);
          if (!empty($term)) {
            $term_entity = reset($term);
            $skills[] = $term_entity->id();
            $count++;
          }
        }
        return $skills;
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('workbc_career_trek')->error('API error: @message', ['@message' => $e->getMessage()]);
    }

    return [];
  }

}
