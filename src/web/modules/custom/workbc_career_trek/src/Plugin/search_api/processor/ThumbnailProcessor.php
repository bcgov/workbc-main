<?php

namespace Drupal\workbc_career_trek\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Plugin\search_api\processor\Property\CustomValueProperty;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;

/**
 * Adds occupational category data from a custom API to the indexed data.
 *
 * @SearchApiProcessor(
 *   id = "thumbnail_processor",
 *   label = @Translation("Thumbnail Processor"),
 *   description = @Translation("Pulls Thumbnail data from an external API and indexes it."),
 *   stages = {
 *     "add_properties" = 0,
 *     "preprocess_index" = -10
 *   }
 * )
 */
class ThumbnailProcessor extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Thumbnail'),
        'description' => $this->t('An Thumbnail field fetched from the Career Trek API.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['thumbnail'] = new CustomValueProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'thumbnail');

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
        $youtube_link = $ssot['career_trek'][0]['youtube_link'] ?? '';
        if (!empty($youtube_link)) {
          // Extract the YouTube video ID from the link (handles youtu.be and youtube.com)
          if (preg_match('~youtu\.be/([a-zA-Z0-9_-]+)~', $youtube_link, $matches)) {
            $video_id = $matches[1];
          }
          elseif (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|v/)|youtu\.be/)([a-zA-Z0-9_-]+)~', $youtube_link, $matches)) {
            $video_id = $matches[1];
          }
          else {
            $video_id = '';
          }

          if (!empty($video_id)) {
            // YouTube thumbnail URL
            $thumbnail_url = "https://img.youtube.com/vi/$video_id/hqdefault.jpg";
            // Destination path in public files
            $destination = 'public://career_trek_thumbnails/' . $video_id . '.jpg';

            // Download and save the image if it doesn't already exist
            $file_system = \Drupal::service('file_system');
            $directory = 'public://career_trek_thumbnails';
            if (!file_exists($file_system->realpath($directory))) {
              $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
            }

            if (!file_exists($file_system->realpath($destination))) {
              try {
                $image_data = @file_get_contents($thumbnail_url);
                if ($image_data !== FALSE) {
                  file_put_contents($file_system->realpath($destination), $image_data);
                }
              }
              catch (\Exception $e) {
                \Drupal::logger('workbc_career_trek')->error('Thumbnail download error: @message', ['@message' => $e->getMessage()]);
              }
            }
            $public_url = \Drupal::service('file_url_generator')->transformRelative($destination);
            return $public_url;
          }
        }
        return '';
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('workbc_career_trek')->error('API error: @message', ['@message' => $e->getMessage()]);
    }

    return '';
  }

}
