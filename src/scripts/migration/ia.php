<?php

require('gc-drupal.php');

use Drupal\path_alias\Entity\PathAlias;
use Drupal\pathauto\PathautoState;

/**
 * Generate nodes for all content type in the WorkBC Refresh IA.
 *
 * Usage:
 * - drush scr scripts/migration/gc-jsonl.php -- -s publish 284269 > scripts/migration/data/ia.jsonl
 * - drush scr scripts/migration/ia
 *
 * Revert:
 * - drush entity:delete node --bundle=page
 * - drush entity:delete node --bundle=landing_page
 * - drush entity:delete menu_link_content
 */

$csv = __DIR__ . '/data/ia.csv';
if (($handle = fopen($csv, "r")) === FALSE) {
    die("Could not open IA spreadsheet $csv");
}
print("Importing IA spreadsheet $csv\n");

$gc_pages = [];
$gc_pages_title_index = [];
if ($data = fopen(__DIR__ . '/data/ia.jsonl', 'r')) {
    print("Reading GC pages\n");
    while (!feof($data)) {
        $gc_page = json_decode(fgets($data));
        if (empty($gc_page)) continue;
        $gc_pages[$gc_page->id] = $gc_page;
        $gc_pages_title_index[strtolower($gc_page->title)] = $gc_page->id;
    }
}

// Setup the front page.
// 1. Set the front page to /front.
\Drupal::configFactory()
    ->getEditable('system.site')
    ->set('page.front', '/front')
    ->save(TRUE);

// 2. Remove all previous aliases to /front.
$fronts = \Drupal::entityTypeManager()->getStorage('path_alias')->loadByProperties(['alias' => '/front']);
foreach ($fronts as $front) {
    $front->delete();
}

// Types that we can import.
$types = [
    'link' => NULL,
    'basic page' => 'page',
    'basic page hero' => 'page',
    'landing page' => 'landing_page'
];

// The columns we are interested in.
const TREE_FIRST = 0;
const TREE_LAST = 5;
const MEGA_MENU = 6;
const DRUPAL_TYPE = 7;
const URL = 12;

// FIRST PASS: Create all the nodes.
print("FIRST PASS\n");
$row_number = 0;
global $pages;
$pages = [];
$path = [];
while (($row = fgetcsv($handle)) !== FALSE) {
    // Skip first header row.
    $row_number++;
    if ($row_number < 2) continue;

    // Detect page title and path to create the hierarchy.
    // We build up the $path array to contain the current hierarchy, discarding "Home".
    $title = NULL;
    $level = NULL;
    for ($c = TREE_LAST; $c >= TREE_FIRST; $c--) {
        if (!empty($row[$c])) {
            $title = trim($row[$c]);
            $level = max(0, $c - TREE_FIRST - 1);
            $path[$level] = $title;
            if ($level < count($path)) {
                $path = array_slice($path, 0, $level + 1);
            }
            break;
        }
    }
    if (empty($title)) {
        print("Skipping empty row $row_number\n");
        continue;
    }

    // Detect a type that we can import.
    $t = strtolower($row[DRUPAL_TYPE]);
    if (empty($t) || !array_key_exists($t, $types)) {
        if (!empty($row[MEGA_MENU]) && empty($row[URL])) {
            // A placeholder page until we have a better way to deal with this entry.
            $type = 'page';
        }
        else {
            $type = NULL;
        }
    }
    else {
        $type = $types[$t];
    }

    print("Processing \"$title\"...\n");
    $fields = [
        'type' => $type,
        'title' => $title,
        'uid' => 1,
        'path' => [
            'pathauto' => PathautoState::CREATE,
        ],
    ];

    // Add content from GatherContent.
    unset($gc_page);
    $gc_page = NULL;
    if (array_key_exists(strtolower($title), $gc_pages_title_index)) {
        print("  Found GatherContent entry\n");
        $gc_page = &$gc_pages[$gc_pages_title_index[strtolower($title)]];

        // Populate fields based on template type.
        // TODO Verify that template type matches Drupal content type.
        switch (trim($gc_page->template)) {
            case "Standard Page":
                $fields = array_merge($fields, [
                    'body' => convertRichText($gc_page->{'Page Content'}),
                    'field_hero_text' => convertRichText($gc_page->{'Page Description'}),
                    'field_hero_image' => array_map('convertImage', array_filter($gc_page->{'Banner Image'})),
                ]);
                break;
            case "Landing Page 1":
            case "Landing Page 2":
            case "Landing Page 3":
            case "Landing Page 4":
            case "Landing Page 5":
            case "Landing Page 6":
                break;
        }
    }

    // Create the node.
    if (!empty($row[URL])) {
        $pages[implode('/', $path)] = [
            'id' => NULL,
            'title' => $title,
            'path' => $path,
            'menu_item' => NULL,
            'mega_menu' => !empty($row[MEGA_MENU]) ? $row_number : NULL,
            'uri' => $row[URL],
            'gc' => NULL,
        ];
        print("  No content: " . implode(' => ', $path) . "\n");
    }
    else if (!empty($type)) {
        $node = Drupal::entityTypeManager()
            ->getStorage('node')
            ->create($fields);
        $node->save();
        $pages[implode('/', $path)] = [
            'id' => $node->id(),
            'title' => $title,
            'path' => $path,
            'menu_item' => NULL,
            'mega_menu' => !empty($row[MEGA_MENU]) ? $row_number : NULL,
            'uri' => NULL,
            'gc' => !empty($gc_page) ? $gc_page->id : NULL,
        ];
        if (!empty($gc_page)) {
            $gc_page->nid = $node->id();
        }
        print("  Created $type: " . implode(' => ', $path) . "\n");
    }
    else {
        print("  Ignoring\n");
    }
}
fclose($handle);

// SECOND PASS:
// - Create the menu hierarchy
// - Link related nodes
print("SECOND PASS\n");

function createMenuEntry($path, $page, &$pages, $menu_name) {
    if (!empty($page['menu_item'])) {
        print("  Menu found. Skipping\n");
        return;
    }
    $menu_link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');

    // Add path alias to /front if this is the home page and exit early.
    $title = $page['title'];
    if (0 === strcasecmp($title, 'Home')) {
        PathAlias::create([
            'path' => "/node/{$page['id']}",
            'alias' => '/front',
            'langcode' => 'en',
        ])->save();
        print("  Home page found. Set it to front page and skipping\n");
        return;
    }

    // Find the parent menu item under which this one will be placed.
    // This is not necessarily the immediate parent in the IA tree - it can be an ancestor.
    $menu_item_parent = NULL;
    $parent_path = array_slice($page['path'], 0, -1);
    while (!empty($parent_path)) {
        $parent = implode('/', $parent_path);
        $page_parent = &$pages[$parent];
        if (empty($page_parent)) {
            print("  Could not find parent node \"$parent\"\n");
        }
        else if (empty($page_parent['menu_item'])) {
            print("  Could not find menu for \"$parent\"\n");
        }
        else {
            $menu_item_parent = $page_parent['menu_item'];
            print("  Parent menu for \"$parent\": $menu_item_parent\n");
            break;
        }
        $parent_path = array_slice($parent_path, 0, -1);
    }

    // Setup the proper URI for this menu entry.
    if (!empty($page['id'])) {
        $link = ['uri' => "entity:node/{$page['id']}"];
    }
    else if (strpos($page['uri'] , 'http') === 0) {
        $link = [
            'uri' => "{$page['uri']}",
            'options' => [
                'attributes' => [
                    'rel' => 'noopener noreferrer',
                    'target' => '_blank',
                ]
            ]
        ];
    }
    else {
        $link = ['uri' => "internal:{$page['uri']}"];
    }
    $menu_link = $menu_link_storage->create([
        'title' => $title,
        'link' => $link,
        'menu_name' => $menu_name,
        'parent' => $menu_item_parent,
        'expanded' => TRUE,
        'weight' => $page['mega_menu']
    ]);
    $menu_link->save();
    $pages[$path]['menu_item'] = $menu_link->getPluginId();
    print("  Menu for \"$title\": {$pages[$path]['menu_item']}\n");
}

foreach ($pages as $path => &$page) {
    print("Processing \"{$page['title']}\"...\n");

    // Insert node in the navigation menu.
    if (!empty($page['mega_menu'])) {
        createMenuEntry($path, $page, $pages, 'main');
    }

    // Add relations from GatherContent.
    if (!empty($page['gc']) && array_key_exists($page['gc'], $gc_pages)) {
        $gc_page = &$gc_pages[$page['gc']];
        if (empty($gc_page)) {
            print( "  Empty GatherContent item {$page['title']}\n");
            continue;
        }

        if (empty($page['id'])) {
            print("  Could not find a Drupal node for {$page['title']}\n");
            continue;
        }

        $node = Drupal::entityTypeManager()
            ->getStorage('node')
            ->load($page['id']);

        if (property_exists($gc_page, 'Related Topics Card')) {
            $node->field_related_topics = convertRelatedTopics($gc_page->{'Related Topics Card'}, $gc_pages);
        }

        $node->save();
    }
}

function convertRelatedTopics($related_topics, &$gc_pages) {
    $field = [];
    if (!empty($related_topics)) foreach (array_filter($related_topics, function($card) {
        return !empty($card->{'Link Anchor'});
    }) as $card) {
        $related_item = NULL;
        if (!preg_match('/^https:\/\/.*?\.gathercontent\.com\/item\/(\d+)$/', $card->{'Link Anchor'}, $related_item)) {
          print("  Could not parse related GatherContent item {$card->{'Link Anchor'}}\n");
          continue;
        }

        $related_id = $related_item[1];
        if (!array_key_exists($related_id, $gc_pages)) {
          print(" Could not find related GatherContent item $related_id\n");
          continue;
        }
        if (empty($gc_pages[$related_id]->nid)) {
          print(" Related GatherContent item $related_id does not have an associated Drupal node\n");
          continue;
        }

        $field[] = ['target_id' => $gc_pages[$related_id]->nid];
    }
    return $field;
}
