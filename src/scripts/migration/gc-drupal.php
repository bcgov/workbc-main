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
  if (empty($image)) return NULL;

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

function convertRichText($text, &$gc_pages = NULL) {
  if (!empty($gc_pages)) {
    $items = convertGatherContentLinks($text, $gc_pages);
    foreach ($items as $item) {
      $options = ['absolute' => FALSE];
      $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $item['nid']], $options);
      $text = str_replace($item['match'], $url->toString(), $text);
    }
  }
  return ['format' => 'full_html', 'value' => $text];
}

function convertMultiline($multiline_field) {
  return array_filter(array_map('trim', explode("\n", $multiline_field)));
}

function convertVideo($url, $extra_fields = []) {
  if (empty($url)) return NULL;

  // Handle youtu.be shortener.
  if (preg_match('/http.?:\/\/youtu.be\/(.*$)/i', $url, $match)) {
    $url = "https://www.youtube.com/watch?v=" . $match[1];
  }

  $medias = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['field_media_oembed_video' => $url]);
  if (!empty($medias)) {
    print("  Found existing media item\n");
    $media = current($medias);
    foreach ($extra_fields as $key => $field) {
      $media->$key = $field;
    }
  }
  else {
    $fields = array_merge([
      'bundle' => 'remote_video',
      'uid' => 1,
      'field_media_oembed_video' => $url,
    ], $extra_fields);
    $media = Drupal::entityTypeManager()
      ->getStorage('media')
      ->create($fields);
  }
  $media->save();
  return ['target_id' => $media->id()];
}

function convertGatherContentLinks($text, &$gc_pages) {
  if (!preg_match_all('/https:\/\/number41media1\.gathercontent\.com\/item\/(\d+)/i', $text, $matches)) {
    return [];
  }

  $items = [];
  foreach ($matches[1] as $m => $match) {
    $item_id = $match;

    // TODO Handle the case where $gc_pages does not contain the item.
    if (!array_key_exists($item_id, $gc_pages)) {
      print("  Could not find related GatherContent item $item_id in existing pages. Trying GC API..." . PHP_EOL);

      $email = $_ENV['GATHERCONTENT_EMAIL'];
      $apiKey = $_ENV['GATHERCONTENT_APIKEY'];
      $client = new \GuzzleHttp\Client();
      $gc = new \Cheppers\GatherContent\GatherContentClient($client);
      $gc
        ->setEmail($email)
        ->setApiKey($apiKey);
      try {
        $item = $gc->itemGet($item_id);
        $nodes = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->loadByProperties(['title' => $item->name]);
        if (!empty($nodes)) {
          $node = current($nodes);
          print("  Found existing node " . $node->id() . PHP_EOL);
          $items[] = array(
            'nid' => $node->id(),
            'match' => $matches[0][$m],
          );
        }
      }
      catch (Exception $e) {
        print("  Could not query GatherContent for item $item_id" . PHP_EOL);
      }
      continue;
    }
    if (empty($gc_pages[$item_id]->nid)) {
      print("  Related GatherContent item $item_id does not have an associated Drupal node" . PHP_EOL);
      continue;
    }
    $items[] = array(
      'nid' => $gc_pages[$item_id]->nid,
      'match' => $matches[0][$m],
    );
  }
  return $items;
}
