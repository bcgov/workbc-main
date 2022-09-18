<?php

require('gc-drupal.php');

use Drupal\path_alias\Entity\PathAlias;
use Drupal\pathauto\PathautoState;

/**
 * Update nodes for GatherContent WorkBC items.
 *
 * Usage:
 * - drush scr scripts/migration/gc-jsonl -- --status publish 284269 > scripts/migration/data/workbc.jsonl
 * - drush scr scripts/migration/workbc
 *
 * Revert:
 * - drush entity:delete node --bundle=blog
 * - drush entity:delete node --bundle=news
 * - drush entity:delete node --bundle=success_story
 */

$file = __DIR__ . '/data/workbc.jsonl';
if (($data = fopen($file, 'r')) === FALSE) {
    die("Could not open GC WorkBC items $file" . PHP_EOL);
}
print("Importing GC WorkBC items $file" . PHP_EOL);

$items = [];
while (!feof($data)) {
    $item = json_decode(fgets($data));
    if (empty($item)) continue;
    $item->process = TRUE;
    $items[$item->id] = $item;
}

foreach ($items as $id => $item) {
    if (!$item->process) continue;

    $title = $item->title;
    print("Processing \"$title\"..." . PHP_EOL);

    $fields = [];

    // Populate standard fields.
    if (property_exists($item, 'Page Content')) {
        $fields['body'] = convertRichText($item->{'Page Content'}, $items);
    }

    if (property_exists($item, 'Page Description')) {
        $fields['field_hero_text'] = convertRichText($item->{'Page Description'}, $items);
    }

    if (property_exists($item, 'Related Topics Blurb')) {
        $fields['field_related_topics_blurb'] = convertRichText($item->{'Related Topics Blurb'}, $items);
    }

    if (property_exists($item, 'Banner Image')) {
        $images = array_map('convertImage', array_filter($item->{'Banner Image'}));
        if (!empty($images)) {
            $fields['field_hero_image'] = current($images);
        }
    }

    // Add related items which come in 2 flavours.
    if (property_exists($item, 'Related Topics Card')) {
        $fields['field_related_topics'] = convertRelatedTopics($item->{'Related Topics Card'}, $items);
    }
    if (property_exists($item, 'Related Topics Link Target')) {
        $fields['field_related_topics'] = convertRelatedTopics(array_map(function($target) {
            $card = new stdClass();
            $card->{'Link Target'} = $target;
            return $card;
        }, array_filter($item->{'Related Topics Link Target'})), $items);
    }

    // Populate remaining fields based on template type.
    switch (trim($item->template)) {
        case "Blog Post, News Post, Success Stories Post":
            if (property_exists($item, 'Date')) {
                $fields['published_date'] = strtotime($item->{'Date'});
            }
            break;
    }

    // Load the corresponding node.
    $nodes = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadByProperties(['title' => $title]);
    if (empty($nodes)) {
        print("  Could not find Drupal node. Attempting to create it..." . PHP_EOL);
        $node = createItem($item);
        if (empty($node)) {
            print("  Could not create Drupal node" . PHP_EOL);
            continue;
        }
    }
    else {
        $node = current($nodes);
    }
    print("  Found existing node " . $node->id() . PHP_EOL);
    foreach ($fields as $field => $value) {
        $node->$field = $value;
    }
    $node->setPublished(TRUE);
    $node->save();
}

function convertRelatedTopics($related_topics, &$items) {
    $field = [];
    if (!empty($related_topics)) foreach (array_filter($related_topics, function($card) {
        return !empty($card->{'Link Target'});
    }) as $card) {
        $related_items = convertGatherContentLinks($card->{'Link Target'}, $items);
        if (empty($related_items)) {
            print("  Could not parse related GatherContent item {$card->{'Link Target'}}" . PHP_EOL);
            continue;
        }
        $field[] = ['target_id' => current($related_items)['target_id']];
    }
    return $field;
}

function createItem($item) {
    switch (trim($item->template)) {
        case "Blog Post, News Post, Success Stories Post":
            return createBlogNewsSuccessStory($item);
        default:
            break;
    }
    return NULL;
}

function createBlogNewsSuccessStory($item) {
    switch ($item->folder) {
        case "Blog":
            $type = 'blog';
            break;
        case "News":
            $type = 'news';
            break;
        case "Success Stories":
            $type = 'success_story';
            break;
        default:
            print("  Unhandled folder {$item->folder}" . PHP_EOL);
            return;
    }
    $fields = [
        'type' => $type,
        'title' => $item->title,
        'uid' => 1,
        'path' => [
            'pathauto' => PathautoState::CREATE,
        ],
    ];
    $node = Drupal::entityTypeManager()
        ->getStorage('node')
        ->create($fields);
    $node->save();
    print("  Created $type" . PHP_EOL);
    return $node;
}
