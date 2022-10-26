<?php

/**
 * Functions to import GatherContent items into Drupal.
 */

use Drupal\Core\Link;
use Drupal\Core\Url;

function convertCheck($check_field) {
  return array_map(function($field) {
    return $field->label;
  }, $check_field);
}

function convertImage($image) {
  if (empty($image)) return NULL;

  $data = file_get_contents($image->download_url);
  if ($data === FALSE) {
    print("  Could not download file {$image->download_url}" . PHP_EOL);
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

function convertPlainText($text) {
  return str_replace([''], [''], strip_tags(trim($text)));
}

function convertRichText($text, &$items = NULL) {
  if (!empty($items)) {
    foreach (convertGatherContentLinks($text, $items) as $item) {
      $options = ['absolute' => FALSE];
      $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $item['target_id']], $options);
      $text = str_replace($item['match'], $url->toString(), $text);
    }
    foreach (convertEmbeddableLinks($text) as $item) {
      $media = Drupal::entityTypeManager()
        ->getStorage('media')
        ->load($item['target_id']);
      $text = str_replace(
        $item['match'],
        '<drupal-media data-entity-type="media" data-entity-uuid="' . $media->uuid() . '"></drupal-media>',
        $text
      );
    }
    foreach (convertPDFLinks($text) as $item) {
      $text = str_replace($item['match'], $item['replace'], $text);
    }
    // TODO Convert uploaded images within rich text.
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

  $medias = \Drupal::entityTypeManager()
    ->getStorage('media')
    ->loadByProperties(['field_media_oembed_video' => $url]);
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

function convertDrupalLinks($text) {
  if (!preg_match('/^\/(?:[-a-zA-Z0-9()@:%_\+.~#?&\/=]*)$/', $text, $matches)) {
    return [];
  }
  return $matches;
}

function convertGatherContentLinks($text, &$items) {
  if (!preg_match_all('/https:\/\/number41media1\.gathercontent\.com\/item\/(\d+)/i', $text, $matches)) {
    return [];
  }

  $targets = [];
  foreach ($matches[1] as $m => $match) {
    $item_id = $match;

    // Handle the case where $items does not contain the item.
    if (!array_key_exists($item_id, $items)) {
      print("  Could not find related GatherContent item $item_id locally. Trying GC API..." . PHP_EOL);
      $email = getenv('GATHERCONTENT_EMAIL');
      $apiKey = getenv('GATHERCONTENT_APIKEY');
      $client = new \GuzzleHttp\Client();
      $gc = new \Cheppers\GatherContent\GatherContentClient($client);
      $gc
        ->setEmail($email)
        ->setApiKey($apiKey);
      try {
        $item = $gc->itemGet($item_id);
        // If we read a career profile, remove the NOC suffix.
        $item->title = preg_replace('/\s+\(NOC\s+\d+\)$/i', '', $item->name);
        $item->process = FALSE;
        $items[$item_id] = $item;
      }
      catch (Exception $e) {
        print("  Could not query GatherContent item $item_id: {$e->getMessage()}" . PHP_EOL);
        continue;
      }
    }
    if (empty($items[$item_id]->nid)) {
      print("  Could not find Drupal node for related GatherContent item $item_id locally. Trying Drupal API..." . PHP_EOL);
      $nodes = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadByProperties(['title' => convertPlainText($items[$item_id]->title)]);
      if (empty($nodes)) {
        print("  Could not find Drupal node \"{$items[$item_id]->title}\"" . PHP_EOL);
        continue;
      }
      $node = current($nodes);
      $items[$item_id]->nid = $node->id();
    }
    $targets[] = array(
      'target_id' => $items[$item_id]->nid,
      'match' => $matches[0][$m],
    );
  }
  return $targets;
}

function convertEmbeddableLinks($text) {
  // https://uibakery.io/regex-library/url
  if (!preg_match_all('/[^"\'](https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/=]*))[^"\']/', $text, $matches)) {
    return [];
  }

  $providers = \Drupal::service('media.oembed.provider_repository');
  $targets = [];
  foreach ($matches[1] as $url) {
    print("  Verifying embeddable URL $url..." . PHP_EOL);
    foreach ($providers->getAll() as $provider_info) {
      foreach ($provider_info->getEndpoints() as $endpoint) {
        if ($endpoint->matchUrl($url)) {
          print("  Found an embeddable URL $url" . PHP_EOL);
          $video = convertVideo($url);
          $targets[] = array(
            'target_id' => $video['target_id'],
            'match' => $url,
          );
          break 2;
        }
      }
    }
  }
  return $targets;
}

function convertPDFLinks($text) {
  if (!preg_match_all('/https:\/\/www\.workbc\.ca\/getmedia\/[-a-zA-Z0-9]+\/(.*?\.pdf)\.aspx/i', $text, $matches)) {
    return [];
  }

  $targets = [];
  foreach ($matches[0] as $m => $url) {
    $filename = $matches[1][$m];
    $local = __DIR__ . "/data/pdf/$filename";
    if (file_exists($local)) {
      $data = file_get_contents($local);
    }
    else {
      $data = file_get_contents($url);
    }
    if ($data === FALSE) {
      print("  Could not download file {$url}" . PHP_EOL);
      continue;
    }
    $file = \Drupal::service('file.repository')->writeData($data, "public://$filename");
    if (empty($file)) {
      print(" Could not create file $filename\n");
      return NULL;
    }
    $targets[] = [
      'match' => $url,
      'replace' => $file->createFileUrl(),
    ];
  }
  return $targets;
}

function convertLink($text, $url, &$items) {
  $internal = convertGatherContentLinks($url, $items);
  if (!empty($internal)) {
    $target = Url::fromRoute('entity.node.canonical', ['node' => current($internal)['target_id']]);
  }
  else if (str_starts_with($url, '/')) {
    $target = Url::fromUri("internal:$url");
  }
  else {
    $target = Url::fromUri($url);
  }
  return [
    'title' => $text,
    'uri' => $target->toUriString()
  ];
}
