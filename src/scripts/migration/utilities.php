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

    $file = createFile($image->download_url, 'assets', $image->filename, str_replace('/', '_', $image->file_id) . '-' . $image->filename);
    if (empty($file)) return NULL;

    $title = pathinfo($image->filename, PATHINFO_FILENAME);
    return [
        'target_id' => $file->id(),
        'alt' => empty($image->alt_text) ? $title : $image->alt_text,
        'title' => $title,
        'uuid' => $file->uuid(),
        'uri' => $file->createFileUrl(),
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
    foreach (convertWorkBCImageLinks($text) as $item) {
        $text = str_replace($item['match'], $item['replace'], $text);
    }
    foreach (convertWorkBCFileLinks($text) as $item) {
        $text = str_replace($item['match'], $item['replace'], $text);
    }
    foreach (convertWorkBCGeneralLinks($text) as $item) {
        $text = str_replace($item['match'], $item['replace'], $text);
    }
    foreach (convertGatherContentImageLinks($text) as $item) {
        $text = str_replace($item['match'], $item['replace'], $text);
    }
    return ['format' => 'full_html', 'value' => $text];
}

function convertMultiline($multiline_field) {
    return array_filter(array_map('trim', explode("\n", $multiline_field)));
}

function convertVideo($url, $extra_fields = []) {
    if (empty($url)) return NULL;

    // Handle youtu.be shortener.
    if (preg_match('/https?:\/\/youtu.be\/(.*$)/i', $url, $match)) {
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

function convertGatherContentLinks($text, &$items) {
    if (!preg_match_all('/https:\/\/number41media1\.gathercontent\.com\/item\/(\d+)/i', $text, $matches)) {
        return [];
    }

    $targets = [];
    foreach ($matches[1] as $m => $match) {
        $item_id = $match;

        // Handle the case where $items does not contain the item.
        // All items should now be downloaded.
        if (!array_key_exists($item_id, $items)) {
            print("  Error: Could not find GatherContent item $item_id" . PHP_EOL);
            continue;
            // $email = getenv('GATHERCONTENT_EMAIL');
            // $apiKey = getenv('GATHERCONTENT_APIKEY');
            // $client = new \GuzzleHttp\Client();
            // $gc = new \Cheppers\GatherContent\GatherContentClient($client);
            // $gc
            // ->setEmail($email)
            // ->setApiKey($apiKey);
            // try {
            //     $item = $gc->itemGet($item_id);
            //     // If we read a career profile, remove the NOC suffix.
            //     $item->title = preg_replace('/\s+\(NOC\s+\d+\)$/i', '', $item->name);
            //     $item->process = FALSE;
            //     $items[$item_id] = $item;
            // }
            // catch (Exception $e) {
            //     print("  Error: Could not query GatherContent item $item_id: {$e->getMessage()}" . PHP_EOL);
            //     continue;
            // }
        }
        if (empty($items[$item_id]->nid)) {
            $node = findNode($items[$item_id]->title, $items[$item_id]->folder);
            if (empty($node)) {
                print("  Error: Could not find Drupal node for GatherContent item $item_id" . PHP_EOL);
                continue;
            }
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
    $matches = [];
    if (!preg_match_all('/[^"\'](https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/=]*))[^"\']/', $text, $matches)) {
        return [];
    }

    $providers = \Drupal::service('media.oembed.provider_repository');
    $targets = [];
    foreach ($matches[1] as $url) {
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

function convertWorkBCFileLinks($text) {
    $matches = [];
    if (!preg_match_all('/https:\/\/www.workbc.ca\/getmedia\/[[:alnum:]-]+\/([^"#]+.(?:pdf|docx)).aspx/i', $text, $matches)) {
        return [];
    }

    $targets = [];
    foreach ($matches[0] as $m => $url) {
        $file = createFile($url, 'pdf', $matches[1][$m], $matches[1][$m]);
        if (empty($file)) continue;

        $targets[] = [
            'match' => $url,
            'replace' => $file->createFileUrl(),
        ];
    }
    return $targets;
}

function convertWorkBCGeneralLinks($text) {
    $matches = [];
    if (!preg_match_all('/https:\/\/(?:www.)?workbc.ca(\/[^"#]+)/i', $text, $matches)) {
        return [];
    }

    $targets = [];
    foreach ($matches[0] as $m => $url) {
        // SPECIAL CASES: Exclude these from results.
        foreach ([
            '/careercompass',
            '/careersearchtool',
            '/careertransitiontool',
        ] as $exclude) {
            if (str_starts_with($matches[1][$m], $exclude)) {
                continue 2;
            }
        }
        $targets[] = [
            'match' => $url,
            'replace' => $matches[1][$m],
        ];
    }
    return $targets;
}

function convertWorkBCImageLinks($text) {
    $matches = [];
    if (!preg_match_all('/<figure>.*?(https:\/\/www.workbc.ca\/getmedia\/[[:alnum:]-]+\/([^"#?]+.(?:jpg|png)).aspx|https:\/\/www.workbc.ca\/getattachment\/[[:alnum:]\/-]+\/([^"#?]+.(?:jpg|png)).aspx).*?<\/figure>/i', $text, $matches)) {
        return [];
    }

    $targets = [];
    foreach ($matches[0] as $m => $match) {
        $filename = !empty($matches[2][$m]) ? $matches[2][$m] : $matches[3][$m];
        $file = createFile($matches[1][$m], 'assets', $filename, $filename);
        if (empty($file)) continue;

        // Build an <img> tag based on the image we found.
        $alt = str_replace('"', '', pathinfo($filename, PATHINFO_FILENAME));
        $tag = '<img alt="' . $alt . '" data-entity-type="file" data-entity-uuid="' . $file->uuid() . '" src="' . $file->createFileUrl() . '" />';

        $targets[] = [
            'match' => $match,
            'replace' => $tag,
        ];
    }
    return $targets;
}

function convertGatherContentImageLinks($text) {
    if (!preg_match_all('/<figure>.*?https:\/\/assets.gathercontent.com\/([[:alnum:]]+\/[[:alnum:]]+)\?(.*?)<\/figure>/i', $text, $matches)) {
        return [];
    }

    $targets = [];
    foreach ($matches[1] as $m => $match) {
        $image_id = $match;
        $email = getenv('GATHERCONTENT_EMAIL');
        $apiKey = getenv('GATHERCONTENT_APIKEY');
        $projectId = getenv('GATHERCONTENT_PROJECT_ID');
        $client = new \GuzzleHttp\Client();
        try {
            $response = $client->request('GET', "https://api.gathercontent.com/projects/$projectId/files/$image_id", [
                'headers' => [
                    'accept' => 'application/vnd.gathercontent.v2+json',
                    'authorization' => 'Basic ' . base64_encode($email . ':' . $apiKey),
                ],
            ]);
            $image = json_decode($response->getBody())->data;
            $image->file_id = $image_id;
            $item = convertImage($image);

            // Build an <img> tag based on the image we found.
            $tag = '<img alt="' . $item['alt'] . '" data-entity-type="file" data-entity-uuid="' . $item['uuid'] . '" src="' . $item['uri'] . '"';

            // Detect image width and/or height.
            if (preg_match_all('/(?:^|&)(w|h)=(\d+)/', $matches[2][$m], $attrs)) {
                $autos = ['width' => 'width="auto"', 'height' => 'height="auto"'];
                foreach ($attrs[1] as $a => $attr) {
                    if ($attr === 'w') {
                        $tag .= ' width="' . $attrs[2][$a] . '"';
                        unset($autos['width']);
                    }
                    else if ($attr === 'h') {
                        $tag .= ' height="' . $attrs[2][$a] . '"';
                        unset($autos['height']);
                    }
                }
                $tag .= ' ' . join(' ', $autos);
            }

            $tag .= ' />';

            $targets[] = [
                'match' => $matches[0][$m],
                'replace' => $tag,
            ];
        }
        catch (Exception $e) {
            print("  Error: Could not query GatherContent image $image_id: {$e->getMessage()}" . PHP_EOL);
            continue;
        }
    }
    return $targets;
}

function convertLink($text, $url, &$items) {
    $internal = convertGatherContentLinks($url, $items);
    if (!empty($internal)) {
        $target = "internal:/node/" . current($internal)['target_id'];
    }
    else {
        $internal = convertWorkBCFileLinks($url);
        if (!empty($internal)) {
            $target = "internal:" . current($internal)['replace'];
        }
        else {
            $internal = convertWorkBCGeneralLinks($url);
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

function createFile($url, $dataFolder, $dataFilename, $finalFilename) {
    $local = __DIR__ . "/data/$dataFolder/$dataFilename";
    $data = FALSE;
    if (file_exists($local)) {
        $data = file_get_contents($local);
    }
    else if (!empty($url)) {
        $data = file_get_contents($url);
    }
    if ($data === FALSE) {
        print("  Error: Could not download file $local ($url)" . PHP_EOL);
        return NULL;
    }
    $file = \Drupal::service('file.repository')->writeData($data, "public://$finalFilename", \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);
    if (empty($file)) {
        print("  Error: Could not create file $finalFilename" . PHP_EOL);
        return NULL;
    }
    return $file;
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

function findNode($title, $parent) {
    // Identifying an existing node by title is sometimes not enough because some pages have non-unique titles.
    // If so, we identify the node by its position in the navigation menu:
    // 1. Identify the parent menu item in the navigation menu (assumed to be unique)
    // 2. Identify the node menu item in the navigation menu
    // 3. Retrieve the node entity from the menu item
    $nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties(['title' => $title]);
    if (empty($nodes)) {
        print("  Warning: Could not find any node with title \"$title\"" . PHP_EOL);
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
