<?php

use Drupal\redirect\Entity\Redirect;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use Drupal\media\MediaStorage;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Add redirections of the form http://www.workbc.ca/Job-Seekers/Career-Profiles/[NOC]
 *
 * As per ticket WR-1531.
 */
function workbc_custom_post_update_1531(&$sandbox = NULL) {
  if (!isset($sandbox['nocs'])) {
    $connection = \Drupal::database();
    $query = $connection->select('node__field_noc');
    $query->condition('node__field_noc.bundle', 'career_profile');
    $query->addField('node__field_noc', 'entity_id');
    $query->addField('node__field_noc', 'field_noc_value');
    $sandbox['nocs'] = $query->execute()->fetchAll();
    $sandbox['count'] = count($sandbox['nocs']);
  }

  $noc = array_pop($sandbox['nocs']);
  if (!empty($noc)) {
    $source = 'Job-Seekers/Career-Profiles/' . $noc->field_noc_value . '.aspx';
    $target_url = 'internal:/node/' . $noc->entity_id;
    try {
      Redirect::create([
        'redirect_source' => $source,
        'redirect_redirect' => $target_url,
        'language' => 'und',
        'status_code' => '301',
      ])->save();
      Redirect::create([
        'redirect_source' => str_replace('.aspx', '', $source),
        'redirect_redirect' => $target_url,
        'language' => 'und',
        'status_code' => '301',
      ])->save();
    }
    catch (Exception $e) {
      // Do nothing.
    }
  }

  $sandbox['#finished'] = empty($sandbox['nocs']) ? 1 : ($sandbox['count'] - count($sandbox['nocs'])) / $sandbox['count'];
  return t('[WR-1531] Added new redirection for career profile.');
}

/**
 * Migrate hero images.
 *
 * As per ticket WR-1566.
 */
function workbc_custom_post_update_1566_hero_images(&$sandbox = NULL) {
  if (!isset($sandbox['fields'])) {
    $connection = \Drupal::database();
    $query = $connection->select('node__field_hero_image');
    $query->addField('node__field_hero_image', 'entity_id');
    $query->addField('node__field_hero_image', 'field_hero_image_target_id');
    $query->addField('node__field_hero_image', 'field_hero_image_alt');
    $query->addField('node__field_hero_image', 'field_hero_image_title');
    $sandbox['fields'] = $query->execute()->fetchAll();
    $sandbox['count'] = count($sandbox['fields']);
  }

  $field = array_shift($sandbox['fields']);
  if (!empty($field)) {
    $file = \Drupal::entityTypeManager()
    ->getStorage('file')
    ->load(intval($field->field_hero_image_target_id));
    $node = Drupal::entityTypeManager()
    ->getStorage('node')
    ->load($field->entity_id);
    $title = $field->field_hero_image_title ?? $field->field_hero_image_alt;
    $alt = $field->field_hero_image_alt ?? $field->field_hero_image_title;
    $fields = [
      'name' => $title,
      'bundle' => 'image',
      'uid' => 1,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $alt,
        'title' => $title,
        'uuid' => $file->uuid(),
        'uri' => $file->createFileUrl(),
      ],
    ];
    $media = Drupal::entityTypeManager()
    ->getStorage('media')
    ->create($fields);
    $media->save();
    $node->field_hero_image_media = [[
      'target_id' => $media->id(),
      'alt' => $alt,
      'title' => $title,
    ]];
    $node->save();
  }

  $sandbox['#finished'] = empty($sandbox['fields']) ? 1 : ($sandbox['count'] - count($sandbox['fields'])) / $sandbox['count'];
  return t('[WR-1566] Migrated one hero image.');
}

/**
 * Migrate post and publication images.
 *
 * As per ticket WR-1566.
 */
function workbc_custom_post_update_1566_post_images(&$sandbox = NULL) {
  if (!isset($sandbox['fields'])) {
    $connection = \Drupal::database();
    $query = $connection->select('node__field_image');
    $query->addField('node__field_image', 'entity_id');
    $query->addField('node__field_image', 'field_image_target_id');
    $query->addField('node__field_image', 'field_image_alt');
    $query->addField('node__field_image', 'field_image_title');
    $sandbox['fields'] = $query->execute()->fetchAll();
    $sandbox['count'] = count($sandbox['fields']);
  }

  $field = array_shift($sandbox['fields']);
  if (!empty($field)) {
    $file = \Drupal::entityTypeManager()
    ->getStorage('file')
    ->load(intval($field->field_image_target_id));
    $node = Drupal::entityTypeManager()
    ->getStorage('node')
    ->load($field->entity_id);
    $title = $field->field_image_title ?? $field->field_image_alt;
    $alt = $field->field_image_alt ?? $field->field_image_title;
    $fields = [
      'name' => $title,
      'bundle' => 'image',
      'uid' => 1,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $alt,
        'title' => $title,
        'uuid' => $file->uuid(),
        'uri' => $file->createFileUrl(),
      ],
    ];
    $media = Drupal::entityTypeManager()
    ->getStorage('media')
    ->create($fields);
    $media->save();
    $node->field_image_media = [[
      'target_id' => $media->id(),
      'alt' => $alt,
      'title' => $title,
    ]];
    $node->save();
  }

  $sandbox['#finished'] = empty($sandbox['fields']) ? 1 : ($sandbox['count'] - count($sandbox['fields'])) / $sandbox['count'];
  return t('[WR-1566] Migrated one post image.');
}

/**
 * Migrate publication documents.
 *
 * As per ticket WR-1566.
 */
function workbc_custom_post_update_1566_publication_documents(&$sandbox = NULL) {
  if (!isset($sandbox['fields'])) {
    $connection = \Drupal::database();
    $query = $connection->select('node__field_publication');
    $query->addField('node__field_publication', 'entity_id');
    $query->addField('node__field_publication', 'field_publication_target_id');
    $query->addField('node__field_publication', 'field_publication_display');
    $query->addField('node__field_publication', 'field_publication_description');
    $sandbox['fields'] = $query->execute()->fetchAll();
    $sandbox['count'] = count($sandbox['fields']);
  }

  $field = array_shift($sandbox['fields']);
  if (!empty($field)) {
    $file = \Drupal::entityTypeManager()
    ->getStorage('file')
    ->load(intval($field->field_publication_target_id));
    $node = Drupal::entityTypeManager()
    ->getStorage('node')
    ->load($field->entity_id);
    $title = $file->getFilename();
    $fields = [
      'name' => $title,
      'bundle' => 'document',
      'uid' => 1,
      'field_media_document' => [
        'target_id' => $file->id(),
        'title' => $title,
        'display' => $field->field_publication_display,
        'description' => $field->field_publication_description,
        'uuid' => $file->uuid(),
        'uri' => $file->createFileUrl(),
      ],
    ];
    $media = Drupal::entityTypeManager()
    ->getStorage('media')
    ->create($fields);
    $media->save();
    $node->field_publication_media = [[
      'target_id' => $media->id(),
      'display' => $field->field_publication_display,
      'description' => $field->field_publication_description,
    ]];
    $node->save();
  }

  $sandbox['#finished'] = empty($sandbox['fields']) ? 1 : ($sandbox['count'] - count($sandbox['fields'])) / $sandbox['count'];
  return t('[WR-1566] Migrated one publication document.');
}

/**
 * Helper function to update a card paragraph.
 *
 * Paragraphs are stored along with revision ids, so we need to walk up the hirearchy updating the revision ids until the parent node.
 */
function updateParagraph($paragraph) {
  $entity = $paragraph;

  // If this is a paragraph, we need to update the revisions up to the node.
  while ($entity->getEntityTypeId() === 'paragraph') {
    $parent = \Drupal::entityTypeManager()
    ->getStorage($entity->get('parent_type')->value)
    ->load($entity->get('parent_id')->value);
    $references = $parent->{$entity->get('parent_field_name')->value}->getValue();
    // Identify the reference in a multivalued field.
    foreach ($references as &$reference) {
      if ($reference['target_id'] == $entity->id()) {
        $reference['target_revision_id'] = $entity->getRevisionId();
        break;
      }
    }
    $parent->{$entity->get('parent_field_name')->value} = $references;
    $parent->setNewRevision(TRUE);
    $parent->save();
    $entity = $parent;
  }
}

/**
 * Migrate paragraph images.
 *
 * As per ticket WR-1566.
 */
function workbc_custom_post_update_1566_paragraph_images(&$sandbox = NULL) {
  if (!isset($sandbox['fields'])) {
    $connection = \Drupal::database();
    $query = $connection->select('paragraph__field_image');
    $query->addField('paragraph__field_image', 'entity_id');
    $query->addField('paragraph__field_image', 'field_image_target_id');
    $query->addField('paragraph__field_image', 'field_image_alt');
    $query->addField('paragraph__field_image', 'field_image_title');
    $sandbox['fields'] = $query->execute()->fetchAll();
    $sandbox['count'] = count($sandbox['fields']);
  }

  $field = array_shift($sandbox['fields']);
  if (!empty($field)) {
    $file = \Drupal::entityTypeManager()
    ->getStorage('file')
    ->load(intval($field->field_image_target_id));
    $paragraph = Drupal::entityTypeManager()
    ->getStorage('paragraph')
    ->load($field->entity_id);
    $title = $field->field_image_title ?? $field->field_image_alt;
    $alt = $field->field_image_alt ?? $field->field_image_title;
    $fields = [
      'name' => $title,
      'bundle' => 'image',
      'uid' => 1,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $alt,
        'title' => $title,
        'uuid' => $file->uuid(),
        'uri' => $file->createFileUrl(),
      ],
    ];
    $media = Drupal::entityTypeManager()
    ->getStorage('media')
    ->create($fields);
    $media->save();
    $paragraph->field_image_media = [[
      'target_id' => $media->id(),
      'target_revision_id' => $media->getRevisionId(),
      'alt' => $alt,
      'title' => $title,
    ]];
    $paragraph->save();
    updateParagraph($paragraph);
  }

  $sandbox['#finished'] = empty($sandbox['fields']) ? 1 : ($sandbox['count'] - count($sandbox['fields'])) / $sandbox['count'];
  return t('[WR-1566] Migrated one paragraph image.');
}

/**
 * Migrate paragraph links.
 *
 * As per ticket WR-1566.
 */
function workbc_custom_post_update_1566_paragraph_links(&$sandbox = NULL) {
  if (!isset($sandbox['fields'])) {
    $connection = \Drupal::database();
    $query = $connection->select('paragraph__field_link');
    $query->addField('paragraph__field_link', 'entity_id');
    $query->addField('paragraph__field_link', 'field_link_uri');
    $query->addField('paragraph__field_link', 'field_link_title');
    $query->condition('field_link_uri', '%/sites/default/files/%', 'LIKE');
    $sandbox['fields'] = $query->execute()->fetchAll();
    $sandbox['count'] = count($sandbox['fields']);
  }

  $field = array_shift($sandbox['fields']);
  if (!empty($field)) {
    $path = explode('#', urldecode($field->field_link_uri))[0];
    $row = \Drupal::database()->query("
      select fm.fid, fu.id as media_id
      from file_managed fm left join file_usage fu on fu.fid = fm.fid and fu.type = 'media'
      where fm.uri ilike :uri
    ", [':uri' => preg_replace('/(internal:|https:\/\/www\.workbc\.ca)\/sites\/default\/files\//', 'public://', $path)])->fetchObject();

    if (empty($row->fid)) {
      \Drupal::messenger()->addWarning('Empty file for ' . $field->field_link_uri, true);
    }
    else {
      $file = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->load(intval($row->fid));

      // Load or create the media associated with this file.
      if (empty($row->media_id)) {
        $title = $file->getFilename();
        $fields = [
          'name' => $title,
          'bundle' => 'document',
          'uid' => 1,
          'field_media_document' => [
            'target_id' => $file->id(),
            'title' => $title,
            'display' => 1,
            'description' => '',
            'uuid' => $file->uuid(),
            'uri' => $file->createFileUrl(),
          ],
        ];
        $media = Drupal::entityTypeManager()
        ->getStorage('media')
        ->create($fields);
        $media->save();
        $media_id = $media->id();
      }
      else {
        $media_id = $row->media_id;
      }

      // Update the paragraph with a link to download the media instead of the file.
      $paragraph = Drupal::entityTypeManager()
      ->getStorage('paragraph')
      ->load($field->entity_id);
      $paragraph->field_link = [[
        'title' => $field->field_link_title,
        'uri' => 'internal:/media/' . $media_id . '/download?inline',
      ]];
      $paragraph->save();
      updateParagraph($paragraph);
    }
  }

  $sandbox['#finished'] = empty($sandbox['fields']) ? 1 : ($sandbox['count'] - count($sandbox['fields'])) / $sandbox['count'];
  return t('[WR-1566] Migrated one paragraph link.');
}

/**
 * Migrate rich content fields that refer to files.
 *
 * As per tickets WR-1564/WR-1566.
 */
function workbc_custom_post_update_1566_rich_content_fields(&$sandbox = NULL) {
  if (!isset($sandbox['fields'])) {
    $sandbox['fields'] = getUnmanagedFiles();
    $sandbox['count'] = count($sandbox['fields']);
    $sandbox['medias'] = [];
  }

  // Loop on each rich text field that contains file references.
  // The end goal is to replace each file reference with a media reference.
  // If the media had already been created, just go ahead an replace the reference.
  // Otherwise, create a new media item to encapsulate the file.
  // Keep a cache of created file_id => media_id to avoid creating duplicate media items.
  $field = array_shift($sandbox['fields']);
  if (!empty($field)) {
    // Load the entity that contains the field we're processing.
    $entity = Drupal::entityTypeManager()
    ->getStorage($field['entity'])
    ->load($field['entity_id']);

    // Each field has potentially many matches of file references that need replacing.
    foreach ($field['matches'] as $match) {
      $media_id = $match['media_id'];
      if (empty($media_id)) {
        if (empty($match['file_id'])) {
          \Drupal::messenger()->addWarning('Empty file for ' . $match['file_path'], true);
          continue;
        }
        $file = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->load($match['file_id']);

        // First check our cache for an existing media that was created for this file.
        if (array_key_exists($file->id(), $sandbox['medias'])) {
          $media_id = $sandbox['medias'][$file->id()];
          $media = Drupal::entityTypeManager()
          ->getStorage('media')
          ->load($media_id);
        }
        else {
          // Not in cache: Create new media that encapsulates the file.
          $path_parts = pathinfo($file->getFilename());
          $media_fields = [
            'name' => $path_parts['filename'],
            'bundle' => $match['type'] === 'href' ? 'document': 'image',
            'uid' => 1,
            'field_media_image' => [
              'target_id' => $file->id(),
              'alt' => $path_parts['filename'],
              'title' => $path_parts['filename'],
              'uuid' => $file->uuid(),
              'uri' => $file->createFileUrl(),
            ],
            'field_media_document' => [
              'target_id' => $file->id(),
              'title' => $path_parts['filename'],
              'display' => 1,
              'description' => '',
              'uuid' => $file->uuid(),
              'uri' => $file->createFileUrl(),
            ],
          ];
          $media = Drupal::entityTypeManager()
          ->getStorage('media')
          ->create($media_fields);
          $media->save();
          $sandbox['medias'][$file->id()] = $media_id = $media->id();
        }
      }
      else {
        $media = Drupal::entityTypeManager()
        ->getStorage('media')
        ->load($media_id);
      }
      if (empty($media) || empty($media_id)) {
        \Drupal::messenger()->addWarning('Empty media for ' . $match['file_path'], true);
        continue;
      }
      switch ($match['type']) {
        case 'img':
          // Replace img with media embed.
          $embed = '<drupal-media data-entity-type="media" data-entity-uuid="' . $media->uuid() . '"></drupal-media>';
          $entity->{$field['field']}->value = str_replace($match['match'], $embed, $entity->{$field['field']}->value);
          break;
        case 'href':
          // Replace href with media download.
          $url = new Url('media_entity_download.download', ['media' => $media_id], [
            'query' => ['inline' => '']
          ]);
          $entity->{$field['field']}->value = str_replace($match['file_path'], $url->toString(), $entity->{$field['field']}->value);
          break;
      }
    }
    $entity->setNewRevision(TRUE);
    $entity->save();
    updateParagraph($entity);
  }

  $sandbox['#finished'] = empty($sandbox['fields']) ? 1 : ($sandbox['count'] - count($sandbox['fields'])) / $sandbox['count'];
  return t('[WR-1566] Migrated one rich content field.');
}

/**
 * Generate persistent filehash for each file.
 *
 * As per tickets WR-1658/WR-1660.
 */
function workbc_custom_post_update_1660_part_01_generate_filehash(&$sandbox = NULL) {
  if (!isset($sandbox['processed'])) {
    $sandbox['processed'] = 0;
    $sandbox['count'] = \Drupal::database()->query('SELECT COUNT(*) FROM {file_managed}')->fetchField();
  }
  $files = \Drupal::database()->select('file_managed')
    ->fields('file_managed', ['fid'])
    ->orderBy('fid')
    ->range($sandbox['processed'], 1)
    ->execute();
  foreach ($files as $file) {
    // Loading the file object is enough to generate the hash.
    $file = File::load($file->fid);
    $sandbox['processed']++;
  }
  $sandbox['#finished'] = $sandbox['count'] ? $sandbox['processed'] / $sandbox['count'] : 1;
  return t('[WR-1660] Generated file hash for %url.', ['%url' => $file->getFileUri()]);
}

/**
 * Deduplicate medias that refer to the same or to duplicate files.
 *
 * As per ticket WR-1660.
 */
function workbc_custom_post_update_1660_part_02_deduplicate_medias(&$sandbox = NULL) {
  if (!isset($sandbox['duplicates'])) {
    $sandbox['duplicates'] = array_filter(getDuplicateFiles(), function($duplicates) {
      $candidates = array_filter($duplicates, function($d) {
        return !empty($d['media_id']);
      });
      return count($candidates) > 1;
    });
    $sandbox['count'] = count($sandbox['duplicates']);
    $sandbox['delete'] = [];
  }

  // Iterate on each set of duplicate medias.
  // For each set, consider the first media to be the canonical media.
  // For all other medias in the set:
  // - Replace each usage with the canonical media
  // - Delete the media
  $duplicates = array_shift($sandbox['duplicates']);
  if (!empty($duplicates)) {
    $canonical_id = null;
    $candidates = array_filter($duplicates, function($d) use(&$canonical_id, $sandbox) {
      if (!empty($d['media_id'])) {
        if (empty($canonical_id) && !in_array($d['media_id'], array_keys($sandbox['delete']))) {
          $canonical_id = $d['media_id'];
          return false;
        }
        return true;
      }
      return false;
    });
    if (empty($canonical_id)) {
      $d = reset($duplicates);
      \Drupal::messenger()->addWarning('Could not find canonical media for ' . $d['file_path'], true);
    }
    else {
      $canonical = \Drupal::entityTypeManager()
      ->getStorage('media')
      ->load($canonical_id);
      foreach ($candidates as $d) {
        $media = \Drupal::entityTypeManager()
        ->getStorage('media')
        ->load($d['media_id']);

        if (empty($media)) {
          \Drupal::messenger()->addWarning('Could not find media ' . $d['media_id'], true);
          continue;
        }

        // Iterate on usages.
        foreach ($d['usages'] as $usage) {
          if ($usage['type'] === 'deleted') continue;

          $entity = \Drupal::entityTypeManager()
          ->getStorage($usage['entity'])
          ->load($usage['entity_id']);

          if (empty($entity)) {
            \Drupal::messenger()->addWarning('Could not find ' . $usage['entity'] . ':' . $usage['entity_id'], true);
            continue;
          }
          if (empty($entity->{$usage['field']})) {
            \Drupal::messenger()->addWarning('Could not find field ' . $usage['field'] . ' for ' . $usage['entity'] . ':' . $usage['entity_id'], true);
            continue;
          }

          // Replace the identified target media with the canonical media.
          switch ($usage['type']) {
            case 'reference':
              $references = $entity->{$usage['field']}->getValue();
              foreach ($references as &$reference) {
                // Identify the reference in a multivalued field.
                if ($reference['target_id'] == $media->id()) {
                  $reference['target_id'] = $canonical->id();
                  $reference['target_revision_id'] = $canonical->getRevisionId();
                  break;
                }
              }
              $entity->{$usage['field']} = $references;
              break;
            case 'text':
              $value = $entity->{$usage['field']}->value;
              $value = str_replace($media->uuid(), $canonical->uuid(), $value);
              $value = str_replace('/media/' . $media->id() . '/download', '/media/' . $canonical->id() . '/download', $value);
              $entity->{$usage['field']}->value = $value;
              break;
            case 'link':
              $links = $entity->{$usage['field']}->getValue();
              foreach ($links as &$link) {
                $link['uri'] = str_replace('/media/' . $media->id() . '/download', '/media/' . $canonical->id() . '/download', $link['uri']);
              }
              $entity->{$usage['field']} = $links;
              break;
          }

          // Save new revision.
          $entity->setNewRevision(TRUE);
          $entity->save();
          updateParagraph($entity);
        }

        // Remember the duplicate media to delete it at the end.
        $sandbox['delete'][$media->id()] = $media;
      }
    }
  }

  // Delete the duplicate medias if we're done.
  if (empty($sandbox['duplicates'])) {
    \Drupal::entityTypeManager()
    ->getStorage('media')
    ->delete(array_values($sandbox['delete']));
  }

  $sandbox['#finished'] = empty($sandbox['duplicates']) ? 1 : ($sandbox['count'] - count($sandbox['duplicates'])) / $sandbox['count'];
  return t('[WR-1660] Deduplicated one media item.');
}

/**
 * Migrate resource fields to resource nodes.
 *
 * As per ticket WR-1603.
 */
function workbc_custom_post_update_1603_resources(&$sandbox = NULL) {
  if (!isset($sandbox['fields'])) {
    $connection = \Drupal::database();
    $query = $connection->select('node__field_resources');
    $query->addField('node__field_resources', 'entity_id');
    $query->addField('node__field_resources', 'field_resources_uri');
    $query->addField('node__field_resources', 'field_resources_title');
    $sandbox['fields'] = $query->execute()->fetchAll();
    $sandbox['count'] = count($sandbox['fields']);
    $sandbox['resources'] = [];
  }

  $field = array_shift($sandbox['fields']);
  if (!empty($field)) {
    $uri_parts = parse_url($field->field_resources_uri);
    if (empty($uri_parts['host'])) {
      \Drupal::messenger()->addWarning('Could not parse URI ' . $field->field_resources_uri, true);
    }
    else {
      $normalized_uri = strtolower(
        preg_replace('/^www\.|\.com$/i', '', trim($uri_parts['host'])) .
        preg_replace(['/^\/web$/i', '/^\/$/'], '', trim($uri_parts['path'] ?? ''))
      );
      if (array_key_exists($normalized_uri, $sandbox['resources'])) {
        $resource_id = $sandbox['resources'][$normalized_uri];
      }
      else {
        // Create a new resource for this field.
        $resource_fields = [
          'title' => $field->field_resources_title,
          'uid' => 1,
          'type' => 'resource',
          'field_resource' => [
            'title' => $field->field_resources_title,
            'uri' => $field->field_resources_uri,
          ]
        ];
        $resource = Drupal::entityTypeManager()
        ->getStorage('node')
        ->create($resource_fields);
        $resource->setPublished(TRUE);
        $resource->save();
        $resource_id = $resource->id();
        $sandbox['resources'][$normalized_uri] = $resource_id;
      }
      $node = Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($field->entity_id);
      $node->field_resources_reference[] = [
        'target_id' => $resource_id
      ];
      $node->save();
    }
  }

  $sandbox['#finished'] = empty($sandbox['fields']) ? 1 : ($sandbox['count'] - count($sandbox['fields'])) / $sandbox['count'];
  return t('[WR-1603] Migrated one resource field.');
}



/**
 * Delete media item revisions
 *
 * As per ticket WR-1677.
 */
function workbc_custom_post_update_1677(&$sandbox = NULL) {
  if (!isset($sandbox['media'])) {
    $media = \Drupal::entityQuery('media')->execute();
    $sandbox['media'] = $media;
    $sandbox['count'] = count($sandbox['media']);
  }

  $mediaStorage = \Drupal::entityTypeManager()->getStorage('media');

  $id = array_pop($sandbox['media']);
  if (!empty($id)) {
    $media = Media::load($id);

    // current revision id
    $defaultRevision = $media->getRevisionId();

    // get list of revisions for media item
    $result = $mediaStorage->getQuery()
      ->allRevisions()
      ->condition('mid', $id)
      ->sort('vid', 'DESC')
      ->execute();
    $revisions = array_keys($result);

    foreach ($revisions as $rid) {
      // delete if non-current revision
      if ($rid <> $defaultRevision) {
        $mediaStorage->deleteRevision($rid);
      }
    }
  }

  $sandbox['#finished'] = empty($sandbox['media']) ? 1 : ($sandbox['count'] - count($sandbox['media'])) / $sandbox['count'];
  return t('[WR-1677] Delete non-current revisions for one media item.');
}


/**
 * NOC 2021 data migration.
 *
 * As per ticket NOC-227.
 */
function workbc_custom_post_update_227_noc_migration(&$sandbox = NULL) {
  if (!isset($sandbox['concordance'])) {
    $sandbox['concordance'] = loadConcordance();
    $sandbox['count'] = count($sandbox['concordance']);
    $sandbox['last_noc'] = 0;
    $sandbox['last_noc_2016'] = 0;
    $sandbox['last_noc_nid'] = 0;
    $sandbox['last_noc_type'] = "";
    $sandbox['noc_map'] = [];
    $sandbox['lookup'] = [];

    // create lookup map for checking if creating a split is necessary.
    // no split required if an existing career profile will be updated to this
    // NOC 2021 number later in the migration
    foreach ($sandbox['concordance'] as $key => $noc) {
      if ($noc[0] <> "0000" && !empty($noc[3])) {
        $sandbox['lookup'][$noc[3]] = $noc[0];
      }
    }

    // save original Career Profile paths for post migration validation.
    saveCareerProfilePaths();
  }

  $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

  $message = "No action taken.";
  $noc = array_shift($sandbox['concordance']);
  if (!empty($noc)) {

    // split
    if ($noc[0] ==  "0000") {
      // assumes all split NOCs immediately follow the original NOC in concordance
      // only create split if doesn't already exist or will be created later in the migration
      if (!isset($sandbox['noc_map'][$noc[3]]) && !array_key_exists($noc[3], $sandbox['lookup'])) {
        // load source node
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($sandbox['last_noc_nid']);

        // clone node and update
        $split = $node->createDuplicate();
        $split->field_noc = $noc[3];
        $split->title = $noc[4];
        $split->created = time();
        $split->setPublished(TRUE);
        $split->set('moderation_state', 'published');
        $split->save();

        $sandbox['noc_map'][$split->field_noc->value] = ['noc2016' => $split->field_noc_2016->value, 'nid' => $split->id()];

        if ($sandbox['last_noc_type'] == "merge") {
          $message = "NOC 2021 data migration: Split after Merge " . $node->field_noc_2016->value . " -> " . $split->field_noc->value;
        }
        else {
          $message = "NOC 2021 data migration: Split " . $node->field_noc_2016->value . " -> " . $split->field_noc->value;
        }
      }
      else {
        if (isset($sandbox['noc_map'][$noc[3]])) {
          $message = "NOC 2021 data migration: Split already exists " . $sandbox['last_noc_2016'] . " -> " . $noc[3];
        }
        else {
          $message = "NOC 2021 data migration: Split not required " . $sandbox['last_noc_2016'] . " -> " . $noc[3];
        }
      }
    }
    else {
      $connection = \Drupal::database();
      $query = $connection->select('node__field_noc');
      $query->condition('node__field_noc.bundle', 'career_profile');
      $query->condition('node__field_noc.field_noc_value', $noc[0]);
      $query->addField('node__field_noc', 'entity_id');
      $query->addField('node__field_noc', 'field_noc_value');
      $record = $query->execute()->fetchObject();

      $node = \Drupal::entityTypeManager()->getStorage('node')->load($record->entity_id);

      $node->field_noc_2016 = $node->field_noc;

      // if record for NOC 2021 already exists merge
      if (isset($sandbox['noc_map'][$noc[3]])) {
        $node->setPublished(FALSE);
        $node->set('moderation_state', 'archived');
        $node->title = "[ARCHIVED] " . $node->title->value;
        $node->field_noc = "";

        $message = "NOC 2021 data migration: Merge " . $node->field_noc_2016->value . " -> " . $noc[3];
        $sandbox['last_noc_type'] = "merge";

        // save old path alias
        $old_path = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $node->id(), $langcode);
        $node->save();

        $old_path = ltrim($old_path, '/');
        // redirect to NOC profile this NOC is merging with
        Redirect::create([
          'redirect_source' => $old_path,
          'redirect_redirect' => 'internal:/node/'. $sandbox['noc_map'][$noc[3]]['nid'],
          'language' => 'und',
          'status_code' => '301',
        ])->save();

      }
      // else update
      else {
        $node->field_noc = $noc[3];
        $node->title = $noc[4];

        $sandbox['noc_map'][$node->field_noc->value] = ['noc2016' => $node->field_noc_2016->value, 'nid' => $node->id()];
        $sandbox['last_noc_type'] = "update";

        $message = "NOC 2021 data migration: Update " . $node->field_noc_2016->value . " -> " . $node->field_noc->value;

        // save old path alias
        $old_path = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $node->id(), $langcode);
        $node->save();
        // get new path alias
        $new_path = \Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $node->id(), $langcode);
        //create redirect if different
        if ($new_path <> $old_path) {
          $old_path = ltrim($old_path, '/');
          Redirect::create([
            'redirect_source' => $old_path,
            'redirect_redirect' => 'internal:/node/'.$node->id(),
            'language' => 'und',
            'status_code' => '301',
          ])->save();
        }
      }

      // save noc and nid in case needed for split
      $sandbox['last_noc'] = $noc[3];
      $sandbox['last_noc_2016'] = $noc[0];
      $sandbox['last_noc_nid'] = $node->id();
    }
  }

  $sandbox['#finished'] = empty($sandbox['concordance']) ? 1 : ($sandbox['count'] - count($sandbox['concordance'])) / $sandbox['count'];
  return t("[NOC-227] $message");
}


/**
 * NOC 2021 taxonomy migration.
 *
 * As per ticket NOC-227.
 */
function workbc_custom_post_update_227_taxonomy_migration() {

  $updates = array ();
  $updates[] = array (
    'old' => 'Judgment and decision-making',
    'new' => 'Judgment and decision making',
  );
  $updates[] = array (
    'old' => 'Numeracy',
    'new' => 'Mathematics',
  );
  $updates[] = array (
    'old' => 'Operation monitoring',
    'new' => 'Operations monitoring',
  );

  foreach ($updates as $update) {
    $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties(['name' => $update['old'], 'vid' => 'skills']);
    $term = $terms[array_key_first($terms)];
    if ($term) {
      $term->name = $update['new'];
      $term->save();
    }
  }

  $updates = array();
  $updates[] = array(
    'old' => 'Apprenticeship Certificate',
    'teer' => '0',
    'term' => 'Management',
    'description' => 'Management responsibilities'
  );
  $updates[] = array(
    'old' => 'Degree',
    'teer' => '1',
    'term' => 'University Degree',
    'description' => "Completion of a university degree (bachelor's, master's, or doctorate); or Previous experience and expertise in subject matter knowledge from a related occupation found in TEER category 2 (when applicable).",
  );
  $updates[] = array(
    'old' => 'Diploma/Certificate Excluding Apprenticeship',
    'teer' => '2',
    'term' => 'College Diploma or Apprenticeship, 2 or more years',
    'description' => "Completion of a post-secondary education program of two to three years at community college, institute of technology, or CÉGEP; or Completion of an apprenticeship training program of two to five years; or Occupations with supervisory or significant safety (e.g. police officers and firefighters) responsibilities; or Several years of experience in a related occupation from TEER category 3 (when applicable).",
  );
  $updates[] = array(
    'old' => 'High School',
    'teer' => '3',
    'term' => 'College Diploma or Apprenticeship, less than 2 years',
    'description' => "Completion of a post-secondary education program of less than two years at community college, institute of technology or CÉGEP; or Completion of an apprenticeship training program of less than two years; or More than six months of on-the-job training, training courses or specific work experience with some secondary school education; or Several years of experience in a related occupation from TEER category 4 (when applicable).",
  );
  $updates[] = array(
    'old' => 'Less than High School',
    'teer' => '4',
    'term' => 'High School Diploma',
    'description' => "Completion of secondary school; or Several weeks of on-the-job training with some secondary school education; or Experience in a related occupation from TEER category 5 (when applicable).",
  );
  $updates[] = array(
    'old' => '',
    'teer' => '5',
    'term' => 'No Formal Education',
    'description' => "Short work demonstration and no formal educational requirements",
  );

  foreach ($updates as $update) {
    if (!empty($update['old'])) {
      $terms = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $update['old'], 'vid' => 'education']);
      $term = $terms[array_key_first($terms)];
      if ($term) {
        $term->name = $update['term'];
        $term->field_teer = $update['teer'];
        $term->description = $update['description'];
        $term->save();
      }
    }
    else {
      // create new term
      $term = \Drupal\taxonomy\Entity\Term::create([
        'name' => $update['term'],
        'vid' => 'education',
        'description' => $update['description'],
        'field_teer' => $update['teer'],
      ])->save();
    }
  }
  return t('[NOC-227] NOC 2021 taxonomy migration.');
}



/**
 * Career Trek link data migration.
 *
 * As per ticket WBCAMS-14.
 */
function workbc_custom_post_update_14_wbcams_migration(&$sandbox = NULL) {
  if (!isset($sandbox['career_trek'])) {
    $sandbox['career_trek'] = loadCareerTrek();
    $sandbox['career_trek_urls'] = loadCareerTrekUrls();
    $sandbox['count'] = count($sandbox['career_trek']);
  }

  $connection = \Drupal::database();

  $message = "No action taken.";
  $career = array_shift($sandbox['career_trek']);
  if (!empty($career)) {
    if ($career[2] <> "NEW") {   
      $database = \Drupal::database();

      $query = $database->select('media_field_data', 'm');
      $orGroup = $query->orConditionGroup();
      $orGroup->condition('m.name', '%(Episode '.$career[2].')', 'LIKE');
      $orGroup->condition('m.name', '%('.$career[2].')', 'LIKE');
      $query->condition($orGroup);
      $query->condition('m.bundle', 'remote_video', '=');
      $query->fields('m', ['mid', 'name', 'status', 'created']);

      $record = $query->execute()->fetchObject();
      if ($record) {
        $media = Drupal\media\Entity\Media::load($record->mid);
        if ($media) {
          
          $episode = isset($sandbox['career_trek_urls'][$career[2]]) ? $sandbox['career_trek_urls'][$career[2]] : $career[2];
          $target_url = rtrim(\Drupal::config('workbc')->get('careertrek_url'), '/') . "/episode/" .  $episode;
          $media->field_career_trek = $target_url;
          $media->save();
          $message = "Episode " . $career[2] . " Remote Video: " . $media->name->value . " link updated - " . $target_url;
        }
        else {
          $message = $search . " - Media " . $record->mid . " not found";
        }
      }
      else {
        $message = $search . " - Episode not found";
      }
    }      
  }
  
  $sandbox['#finished'] = empty($sandbox['career_trek']) ? 1 : ($sandbox['count'] - count($sandbox['career_trek'])) / $sandbox['count'];
  return t("[WBCAMS-14] $message");
}


/**
 * Assign Skills to Career Profiles.
 *
 * As per ticket WBCAMS-542.
 */
function workbc_custom_post_update_542_career_profile_skills(&$sandbox = NULL) {
  if (!isset($sandbox['career_profiles'])) {
    // load career profiles
    $connection = \Drupal::database();
    $query = $connection->select('node__field_noc');
    $query->condition('node__field_noc.bundle', 'career_profile');
    $query->addField('node__field_noc', 'entity_id');
    $query->addField('node__field_noc', 'field_noc_value');
    $sandbox['career_profiles'] = $query->execute()->fetchAll();
    $sandbox['count'] = count($sandbox['career_profiles']);
  }

  $message = "No action taken.";
  $career = array_shift($sandbox['career_profiles']);
  if (!empty($career)) {
      $database = \Drupal::database();

      $message = "Career Profile ";
      
      $node = Node::load($career->entity_id);
      $ssot_data = ssotCareerProfile($node->get("field_noc")->getString());
      $skills = $ssot_data['skills'];

      if (!is_null($skills[0]['importance'])) {       
        $list = [];
        foreach ($skills as $skill) {
          $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $skill]);
          $term = reset($term);
          $list[] = $term->id();
        }
        $node->field_skills = $list;                  
        $message = "NOC: " . $node->get("field_noc")->getString() . " - Skills updated";
      }
      else {
        $node->field_skills = [];
        $message = "NOC: " . $node->get("field_noc")->getString() . " - No skills associated with Career Profile";
      }
      $node->save();
  }
  
  $sandbox['#finished'] = empty($sandbox['career_profiles']) ? 1 : ($sandbox['count'] - count($sandbox['career_profiles'])) / $sandbox['count'];
  return t("[WBCAMS-542] $message");
}


/**
 * Assign Weight to Media - Remote Video.
 *
 * As per ticket WBCAMS-521.
 */
function workbc_custom_post_update_521_media_weights(&$sandbox = NULL) {
  if (!isset($sandbox['videos'])) {
    // load remote videos
    $database = \Drupal::database();

    $query = $database->select('media_field_data', 'm');
    $query->condition('m.bundle', 'remote_video', '=');
    $query->fields('m', ['mid', 'name', 'status', 'created']);
    $sandbox['videos'] = $query->execute()->fetchAll();
    $sandbox['count'] = count($sandbox['videos']);
  }

  $message = "No action taken.";
  $video = array_shift($sandbox['videos']);

  $media = Media::load($video->mid);
  if ($media) {    
    if (is_null($media->field_weight->value)) {
      $media->field_weight = 0;
      $media->save();
      $message = "Remote Video: " . $media->name->value . " - weight set to 0";
    }
    else {
      $message = "Remote Video: " . $media->name->value . " - weight already set";
    }
  }
  else {
    $message = $search . " - Media " . $record->mid . " not found";
  }

  
  $sandbox['#finished'] = empty($sandbox['videos']) ? 1 : ($sandbox['count'] - count($sandbox['videos'])) / $sandbox['count'];
  return t("[WBCAMS-521] $message");
}