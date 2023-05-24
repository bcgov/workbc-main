<?php

use Drupal\redirect\Entity\Redirect;
use Drupal\Core\Url;
use Drupal\file\Entity\File;

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
    $query->condition('field_link_uri', 'internal:/sites/default/files/%', 'LIKE');
    $sandbox['fields'] = $query->execute()->fetchAll();
    $sandbox['count'] = count($sandbox['fields']);
  }

  $field = array_shift($sandbox['fields']);
  if (!empty($field)) {
    $row = \Drupal::database()->query("
      select fm.fid, fu.id as media_id
      from file_managed fm left join file_usage fu on fu.fid = fm.fid and fu.type = 'media'
      where fm.uri ilike :uri
    ", [':uri' => str_replace('internal:/sites/default/files/', 'public://', $field->field_link_uri)])->fetchObject();

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
