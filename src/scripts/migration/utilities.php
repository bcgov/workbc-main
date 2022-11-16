<?php

/**
 * Utilities to help import GatherContent items into Drupal.
 */

use Drupal\pathauto\PathautoState;
use Drupal\redirect\Entity\Redirect;

function convertCheck($check_field) {
    return array_map(function($field) {
        return $field->label;
    }, $check_field);
}

function convertImage($image) {
    if (empty($image)) return NULL;

    $local = __DIR__ . "/data/assets/{$image->filename}";
    if (file_exists($local)) {
        $data = file_get_contents($local);
    }
    else {
        $data = file_get_contents($image->download_url);
    }
    if ($data === FALSE) {
        print("  Could not download file {$image->download_url}" . PHP_EOL);
        return NULL;
    }
    $filename = str_replace('/', '_', $image->file_id) . '-' . $image->filename;
    $file = \Drupal::service('file.repository')->writeData($data, "public://$filename", \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
    if (empty($file)) {
        print(" Could not create file $filename" . PHP_EOL);
        return NULL;
    }

    $title = pathinfo($image->filename, PATHINFO_FILENAME);
    return [
        'target_id' => $file->id(),
        'alt' => empty($image->alt_text) ? $title : $image->alt_text,
        'title' => $title,
    ];
}

function convertIcon($icon) {
    if (empty($icon)) return NULL;
    $image = convertImage($icon);
    if (empty($image)) return NULL;

    $fields = [
        'name' => $image['title'],
        'bundle' => 'icon',
        'uid' => 1,
        'field_media_image_1' => $image,
    ];
    $media = Drupal::entityTypeManager()
    ->getStorage('media')
    ->create($fields);
    $media->save();
    return [
        'target_id' => $media->id(),
        'alt' => $image['alt'],
        'title' => $image['title'],
    ];
}

function convertRadio($radio_field) {
    return current($radio_field)->label;
}

function convertPlainText($text) {
    return trim(strip_tags($text));
}

function convertRichText($text, &$items = NULL) {
    if (!empty($items)) foreach (convertGatherContentLinks($text, $items) as $item) {
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
    foreach (convertWorkBCLinks($text) as $item) {
        $text = str_replace($item['match'], $item['replace'], $text);
    }
    // TODO Detect links to workbc.ca and convert to Drupal links.
    // TODO Convert uploaded images within rich text.
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
                print("  Error: Could not query GatherContent item $item_id: {$e->getMessage()}" . PHP_EOL);
                continue;
            }
        }
        if (empty($items[$item_id]->nid)) {
            print("  Could not find Drupal node for related GatherContent item $item_id locally. Trying Drupal API..." . PHP_EOL);
            $nodes = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->loadByProperties(['title' => convertPlainText($items[$item_id]->title)]);
            if (empty($nodes)) {
                print("  Error: Could not find Drupal node \"{$items[$item_id]->title}\"" . PHP_EOL);
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
    $matches = [];
    if (!preg_match_all('/https:\/\/www.workbc.ca\/getmedia\/[a-zA-Z0-9-]+\/([^"#]+.(?:pdf|docx)).aspx/', $text, $matches)) {
        return [];
    }

    $targets = [];
    foreach ($matches[0] as $m => $url) {
        $filename = urldecode($matches[1][$m]);
        $local = __DIR__ . "/data/pdf/$filename";
        if (file_exists($local)) {
            $data = file_get_contents($local);
        }
        else {
            $data = file_get_contents($url);
        }
        if ($data === FALSE) {
            print("  Could not download file $url" . PHP_EOL);
            continue;
        }
        $file = \Drupal::service('file.repository')->writeData($data, "public://$filename", \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
        if (empty($file)) {
            print(" Could not create file $filename" . PHP_EOL);
            return NULL;
        }
        $targets[] = [
            'match' => $url,
            'replace' => $file->createFileUrl(),
        ];
    }
    return $targets;
}

function convertWorkBCLinks($text) {
    $matches = [];
    if (!preg_match_all('/https:\/\/(?:www.)?workbc.ca(\/[^"#]+)/', $text, $matches)) {
        return [];
    }

    $targets = [];
    foreach ($matches[0] as $m => $url) {
        $targets[] = [
            'match' => $url,
            'replace' => $matches[1][$m],
        ];
    }
    return $targets;
}

function convertLink($text, $url, &$items) {
    $internal = convertGatherContentLinks($url, $items);
    if (!empty($internal)) {
        $target = "internal:/node/" . current($internal)['target_id'];
    }
    else {
        $internal = convertPDFLinks($url);
        if (!empty($internal)) {
            $target = "internal:" . current($internal)['replace'];
        }
        else {
            $internal = convertWorkBCLinks($url);
            if (!empty($internal)) {
                $target = "internal:" . current($internal)['replace'];
            }
            else if (str_starts_with($url, '/')) {
                $target = "internal:$url";
            }
            else {
                $target = $url;
            }
        }
    }
    return [
        'title' => $text,
        'uri' => $target,
    ];
}

function convertResources($resources) {
    return array_map(function($resource) {
        $uri = convertPlainText($resource->{'Resource Link'});
        $uri = strpos($uri, 'http') !== 0 ? "https://$uri" : $uri;
        return [
            'uri' => $uri,
            'title' => convertPlainText($resource->{'Resource Title'})
        ];
    }, array_filter($resources, function($resource) {
        return !empty($resource->{'Resource Link'}) && !empty($resource->{'Resource Title'});
    }));
}

function createNode($fields, $legacy_urls = null) {
    // Defaults.
    if (!array_key_exists('uid', $fields)) {
        $fields['uid'] = 1;
    }
    if (!array_key_exists('path', $fields)) {
        $fields['path'] = [
            'pathauto' => PathautoState::CREATE,
        ];
    }
    if (!array_key_exists('moderation_state', $fields)) {
        $fields['moderation_state'] = 'published';
    }

    // Create node.
    $node = Drupal::entityTypeManager()
    ->getStorage('node')
    ->create($fields);
    $node->setPublished(TRUE);
    $node->save();

    // Setup redirection.
    createRedirection($legacy_urls, 'internal:/node/' . $node->id());

    print("  Created {$fields['type']}" . PHP_EOL);
    return $node;
}

function createRedirection($legacy_urls, $target_url) {
    if (!empty($legacy_urls)) {
        foreach (array_map('trim', explode(',', $legacy_urls)) as $legacy_url) {
            $count = 0;
            $result = preg_replace('|https://(?:www.)?workbc.ca/(.*)|', '$1', $legacy_url, -1, $count);
            if ($count > 0) {
                Redirect::create([
                    'redirect_source' => $result,
                    'redirect_redirect' => $target_url,
                    'language' => 'und',
                    'status_code' => '301',
                ])->save();
            }
        }
    }
}

function loadNodeByTitleParent($title, $parent) {
    // Identifying an existing node by title is sometimes not enough because some pages have non-unique titles.
    // If so, we identify the node by its position in the navigation menu:
    // 1. Identify the parent menu item in the navigation menu (assumed to be unique)
    // 2. Identify the node menu item in the navigation menu
    // 3. Retrieve the node entity from the menu item
    $nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties(['title' => $title]);
    if (empty($nodes)) {
        print("  Could not find any node with title \"$title\". Ignoring" . PHP_EOL);
        return false;
    }
    else if (count($nodes) > 1) {
        $menu_items_parent = \Drupal::entityTypeManager()
        ->getStorage('menu_link_content')
        ->loadByProperties([
            'title' => $parent,
            'menu_name' => 'main',
        ]);
        if (empty($menu_items_parent)) {
            print("  Error: Could not find parent menu item \"$parent\". Aborting" . PHP_EOL);
            return false;
        }
        else if (count($menu_items_parent) > 1) {
            print("  Error: Found multiple parent menu items \"$parent\". Aborting" . PHP_EOL);
            return false;
        }
        else {
            $menu_items_page = \Drupal::entityTypeManager()
            ->getStorage('menu_link_content')
            ->loadByProperties([
                'parent' => current($menu_items_parent)->getPluginId(),
                'menu_name' => 'main',
                'title' => $title
            ]);
            if (empty($menu_items_page)) {
                print("  Error: Could not find menu item whose parent is \"$parent\". Aborting" . PHP_EOL);
                return false;
            }
            else {
                $nid = (int) filter_var(current($menu_items_page)->link->uri, FILTER_SANITIZE_NUMBER_INT);
                $node = Drupal::entityTypeManager()
                ->getStorage('node')
                ->load($nid);
            }
        }
    }
    else {
        $node = current($nodes);
    }
    return $node;
}
