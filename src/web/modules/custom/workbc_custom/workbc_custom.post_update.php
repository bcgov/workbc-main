<?php

use Drupal\redirect\Entity\Redirect;

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
  }

  $sandbox['#finished'] = empty($sandbox['fields']) ? 1 : ($sandbox['count'] - count($sandbox['fields'])) / $sandbox['count'];
  return t('[WR-1566] Migrated one paragraph image.');
}
