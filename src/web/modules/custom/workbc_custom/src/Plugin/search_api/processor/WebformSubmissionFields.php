<?php

namespace Drupal\workbc_custom\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * Search API Processor for indexing Webform Submission fields.
 *
 * @SearchApiProcessor(
 *   id = "webform_submission_fields",
 *   label = @Translation("Webform Submission fields"),
 *   description = @Translation("Switching on will enable indexing submitted values of a Webform Submission."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class WebformSubmissionFields extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = [];

    if ($datasource) {
      $definition = $datasource->getPluginDefinition();
      $configuration = $datasource->getConfiguration();
      if ($definition['id'] === 'entity' && $definition['entity_type'] === 'webform_submission') {
        foreach ($configuration['bundles']['selected'] as $bundle) {
          /** @var \Drupal\webform\WebformInterface $webform */
          $webform = \Drupal::entityTypeManager()->getStorage('webform')->load($bundle);
          foreach ($webform->getElementsInitializedFlattenedAndHasValue() as $key => $element) {
            $properties["webform_submission_search_api__{$bundle}__{$key}"] = new ProcessorProperty([
              'label' => $element['#title'],
              'description' =>  $element['#description'] ?? '',
              'type' => 'string',
              'processor_id' => $this->getPluginId(),
            ]);
          }
        }
      }
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    /** @var \Drupal\webform\Entity\WebformSubmission $submission */
    $submission = $item->getOriginalObject()->getValue();
    foreach ($item->getFields() as $field) {
      [, , $key] = explode('__', $field->getPropertyPath());
      $field->addValue($submission->getElementData($key));
    }
  }

}
