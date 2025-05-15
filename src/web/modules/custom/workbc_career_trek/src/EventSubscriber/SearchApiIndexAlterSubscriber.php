<?php

namespace Drupal\workbc_career_trek\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\search_api\Event\IndexingItemsEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\node\Entity\Node;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValue;
use Drupal\search_api\Item\Item;
use Drupal\search_api\Item\Field;

/**
 * Event subscriber to alter Search API indexed data.
 *
 * This subscriber ensures that for each node with a NOC ID, we fetch all
 * related SSOT content (by episode_num) and index them separately in Solr.
 * Only items with an episode_num value are indexed.
 *
 * Instead of cloning the item for each episode_num, assign each episode_num
 * to the next available item that does not already have a career_trek SSOT record.
 * If there are more SSOT records than items, create new items for the extra records.
 * All SSOT records will be added to Solr.

 */
class SearchApiIndexAlterSubscriber implements EventSubscriberInterface {

  /**
   * Alters indexed items before they are sent to Solr.
   *
   * @param \Drupal\search_api\Event\IndexingItemsEvent $event
   */
  public function onIndexingItems(IndexingItemsEvent $event) {
    $items = $event->getItems();
    $new_items = [];
    $used_episode_nums = [];
    $item_queue = [];

    // Prepare a queue of items that can be reused for SSOT records.
    foreach ($items as $item_id => $item) {
      $item_queue[] = [$item_id, $item];
    }

    foreach ($items as $item_id => $item) {
      $entity = $item->getOriginalObject()->getValue();

      if ($entity instanceof Node && $entity->hasField('field_noc')) {
        $noc_id = $entity->get('field_noc')->value;
        if ($noc_id) {
          $ssot_records = $this->getSsotRecordsByNoc($noc_id);
          if (!empty($ssot_records) && is_array($ssot_records)) {
            foreach ($ssot_records as $ssot_row) {
              if (empty($ssot_row['episode_num'])) {
                continue;
              }
              $episode_num = $ssot_row['episode_num'];
              if (in_array($episode_num, $used_episode_nums)) {
                continue;
              }
              $used_episode_nums[] = $episode_num;
              $unique_key = $entity->id() . '-' . $episode_num;

              // Use next available item from the queue, or clone if not enough.
              if (!empty($item_queue)) {
                list($reuse_item_id, $reuse_item) = array_shift($item_queue);
                $teer = querySSoT('education?noc=eq.' . $noc_id);
                if(!empty($teer)) {
                  $teer = $teer[0]['teer'];
                  $fields = $reuse_item->getFields();
                  if (isset($fields['minimum_education'])) {
                    // Blank out previous values, then add the new value.
                    $fields['minimum_education']->setValues([]);
                    $fields['minimum_education']->addValue("$teer");
                  }
                }
                $occupational_categories = querySSoT('fyp_categories_interests_nocs?noc_2021=eq.' . $noc_id);
                if (!empty($occupational_categories) && is_array($occupational_categories)) {
                  $fields = $reuse_item->getFields();
                  if (isset($fields['occupational_category_api_field'])) {
                    // Blank out previous values, then add all categories as values.
                    $fields['occupational_category_api_field']->setValues([]);
                    foreach ($occupational_categories as $cat_row) {
                      if (!empty($cat_row['category'])) {
                        $fields['occupational_category_api_field']->addValue($cat_row['category']);
                      }
                    }
                  }
                }

                $annual_salary = querySSoT('wages?noc=eq.' . $noc_id);
                if(!empty($annual_salary)) {
                  $annual_salary = $annual_salary[0]['calculated_median_annual_salary'];
                  $fields = $reuse_item->getFields();
                  if (isset($fields['annual_salary'])) {
                    // Blank out previous values, then add the new value.
                    $fields['annual_salary']->setValues([]);
                    $fields['annual_salary']->addValue((string)$annual_salary);
                  }
                }
                // Overwrite the fields for this SSOT record.
                $this->setItemEpisodeNum($reuse_item, $episode_num, $ssot_row);
                $this->setItemUniqueId($reuse_item, $unique_key);
                // Set the item id to the unique key so that it appears in the index view.
                $this->setItemId($reuse_item, $unique_key);
                // IMPORTANT: Deep clone the item to avoid reference issues.
                $cloned_item = $this->deepCloneItem($reuse_item);
                $new_items[$unique_key] = $cloned_item;
              }
              else {
                // Not enough original items, so create a new one based on the first item.
                // This is a deep clone to avoid reference issues.
                $index = $item->getIndex();
                $datasource = $item->getDatasource();
                $original_object = $item->getOriginalObject();
                $new_item_id = $item_id . '-' . $episode_num;
                $new_item = new Item($index, $new_item_id, $datasource, $original_object);

                // Copy all fields from the original item.
                foreach ($item->getFields() as $field_id => $field) {
                  $new_field = new Field($index, $field_id, $datasource, $new_item);
                  $new_field->setValues($field->getValues());
                  $new_item->setField($field_id, $new_field);
                }

                $this->setItemEpisodeNum($new_item, $episode_num, $ssot_row);
                $this->setItemUniqueId($new_item, $unique_key);
                // Set the item id to the unique key so that it appears in the index view.
                $this->setItemId($new_item, $unique_key);
                $new_items[$unique_key] = $new_item;
              }
            }
          }
        }
      }
      // If not a node or doesn't have field_noc, do not index the item.
    }

    // If for some reason no new items were created (e.g. no NOC/episodes), fallback to original items.
    if (empty($new_items)) {
      $event->setItems($items);
    }
    else {
      $event->setItems($new_items);
    }
  }

  /**
   * Deep clone a Search API item and all its fields to avoid reference issues.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   * @return \Drupal\search_api\Item\ItemInterface
   */
  protected function deepCloneItem($item) {
    $index = $item->getIndex();
    $datasource = $item->getDatasource();
    $original_object = $item->getOriginalObject();
    $item_id = $item->getId();
    $new_item = new Item($index, $item_id, $datasource, $original_object);

    foreach ($item->getFields() as $field_id => $field) {
      $new_field = new Field($index, $field_id, $datasource, $new_item);
      $new_field->setValues($field->getValues());
      $new_item->setField($field_id, $new_field);
    }
    return $new_item;
  }

  /**
   * Helper to set a unique ID as a custom field on the Search API item.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   * @param string $unique_id
   */
  protected function setItemUniqueId($item, $unique_id) {
    $fields = $item->getFields();
    $field_id = 'custom_unique_id';
    $field_id_nid = 'node_id';
    $nid = explode('-',$unique_id);
    if (isset($fields[$field_id])) {
      // Blank out previous values, then add the new value.
      $fields[$field_id]->setValues([]);
      $fields[$field_id]->addValue($unique_id);
    }
    else {
      $field = new \Drupal\search_api\Item\Field($item->getIndex(), $field_id);
      $field->setValues([]);
      $field->addValue($unique_id);
      $item->setField($field_id, $field);
    }
    if (isset($fields[$field_id_nid])) {
      // Blank out previous values, then add the new value.
      $fields[$field_id_nid]->setValues([]);
      $fields[$field_id_nid]->addValue($nid[0]);
    }
    else {
      $field = new \Drupal\search_api\Item\Field($item->getIndex(), $field_id_nid);
      $field->setValues([]);
      $field->addValue($nid[0]);
      $item->setField($field_id_nid, $field);
    }
  }

  /**
   * Helper to set the item id property (for Search API) to ensure it appears in the index view.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   * @param string $new_id
   */
  protected function setItemId($item, $new_id) {
    // The Item class stores the id as a protected property, so we need to use reflection.
    $reflection = new \ReflectionObject($item);
    if ($reflection->hasProperty('id')) {
      $property = $reflection->getProperty('id');
      $property->setAccessible(true);
      $property->setValue($item, $new_id);
    }
  }

  /**
   * Helper to fetch all SSOT records for a given NOC ID.
   *
   * @param string $noc_id
   * @return array
   */
  protected function getSsotRecordsByNoc($noc_id) {
    return querySSoT('career_trek?noc_2021=eq.' . $noc_id);
  }

  /**
   * Helper to set the episode_num and other SSOT data on the Search API item.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   * @param string $episode_num
   * @param array $ssot_row
   */
  protected function setItemEpisodeNum($item, $episode_num, array $ssot_row) {
    $fields = $item->getFields();
    if (isset($fields['episode_num'])) {
      // Blank out previous values, then add the new value.
      $fields['episode_num']->setValues([]);
      $fields['episode_num']->addValue($episode_num);
    }
    else {
      $field = new \Drupal\search_api\Item\Field($item->getIndex(), 'episode_num');
      $field->setValues([]);
      $field->addValue($episode_num);
      $item->setField('episode_num', $field);
    }

    $field_map = [
      'ssot_title'     => 'title_2021',
      'career_noc'     => 'noc_2021',
      'episode_title'      => 'episode_title',
      'description'      => 'career_description',
      'youtube_url'      => 'youtube_link',
      'custom_location'  => 'location',
      'region_api_field' => 'region',
    ];
    foreach ($field_map as $search_api_field => $ssot_field) {
      if (isset($ssot_row[$ssot_field])) {
        if (isset($fields[$search_api_field])) {
          // Blank out previous values, then add the new value.
          $fields[$search_api_field]->setValues([]);
          if ($search_api_field == "ssot_title" || $search_api_field == "career_noc") {
            $fields[$search_api_field]->addValue(new TextValue($ssot_row[$ssot_field]));
          }
          elseif ($search_api_field == "youtube_url") {
            $fields[$search_api_field]->addValue(new TextValue($ssot_row[$ssot_field]));
            $this->setThumbnailField($item, $fields, $ssot_row[$ssot_field]);
          }
          else {
            $fields[$search_api_field]->addValue($ssot_row[$ssot_field]);
          }
        }
        else {
          $field = new \Drupal\search_api\Item\Field($item->getIndex(), $search_api_field);
          $field->setValues([]);
          if ($search_api_field == "ssot_title" || $search_api_field == "career_noc") {
            $field->addValue(new TextValue($ssot_row[$ssot_field]));
          }
          elseif ($search_api_field == "youtube_url") {
            $field->addValue(new TextValue($ssot_row[$ssot_field]));
            $item->setField($search_api_field, $field);
            $this->setThumbnailField($item, $item->getFields(), $ssot_row[$ssot_field]);
            continue;
          }
          else {
            $field->addValue($ssot_row[$ssot_field]);
          }
          $item->setField($search_api_field, $field);
        }
      }
    }
  }

  /**
   * Helper to set the thumbnail field for a YouTube video.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   * @param array $fields
   * @param string $youtube_url
   */
  protected function setThumbnailField($item, &$fields, $youtube_url) {
    $video_id = NULL;
    if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $youtube_url, $matches)) {
      $video_id = $matches[1];
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
        $file_system->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
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

      // Save the thumbnail URL in the "thumbnail" Solr field
      if (isset($fields['thumbnail'])) {
        $fields['thumbnail']->setValues([]);
        $fields['thumbnail']->addValue($public_url);
      }
      else {
        $thumbnail_field = new \Drupal\search_api\Item\Field($item->getIndex(), 'thumbnail');
        $thumbnail_field->setValues([]);
        $thumbnail_field->addValue($public_url);
        $item->setField('thumbnail', $thumbnail_field);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiEvents::INDEXING_ITEMS => ['onIndexingItems'],
    ];
  }
}
