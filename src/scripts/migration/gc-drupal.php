<?php

/**
 * Functions to import GatherContent items into Drupal.
 */

function convertCheck($check_field) {
  return array_map(function($field) {
    return $field->label;
  }, $check_field);
}

function convertImage($image) {
  $data = file_get_contents($image->download_url);
  if ($data === FALSE) {
    print("  Could not download file {$image->download_url}\n");
    return NULL;
  }
  $filename = str_replace('/', '_', $image->file_id) . '-' . $image->filename;
  $file = \Drupal::service('file.repository')->writeData($data, "public://$filename");
  if (empty($file)) {
    print(" Could not create file $filename\n");
    return NULL;
  }

  $title = pathinfo($image->filename, PATHINFO_FILENAME);
  return [
    'target_id' => $file->id(),
    'alt' => empty($image->alt_text) ? $title : $image->alt_text,
    'title' => $title,
  ];
}

function convertRadio($radio_field) {
  return current($radio_field)->label;
}

function convertRichText($text) {
  return ['format' => 'full_html', 'value' => $text];
}

function convertMultiline($multiline_field) {
  return array_filter(array_map('trim', explode("\n", $multiline_field)));
}

function convertVideo($url) {
  $fields = [
    'bundle' => 'remote_video',
    'uid' => 1,
    'field_media_oembed_video' => $url,
  ];
  $media = Drupal::entityTypeManager()
    ->getStorage('media')
    ->create($fields);
  $media->save();
  return ['target_id' => $media->id()];
}
