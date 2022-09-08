<?php

use Drupal\path_alias\Entity\PathAlias;
use Drupal\pathauto\PathautoState;

/**
 * Generate nodes for all content types in the WorkBC IA.
 *
 * Usage:
 * - drush scr scripts/migration/ia
 *
 * Revert:
 * - drush entity:delete node --bundle=page
 * - drush entity:delete menu_link_content
 */

$file = __DIR__ . '/data/ia.csv';
if (($data = fopen($file, 'r')) === FALSE) {
    die("Could not open IA spreadsheet $file" . PHP_EOL);
}
print("Importing IA spreadsheet $file" . PHP_EOL);

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
    'landing page' => 'page'
];

// The columns we are interested in.
const TREE_FIRST = 0;
const TREE_LAST = 5;
const MEGA_MENU = 6;
const DRUPAL_TYPE = 7;
const URL = 12;
const PAGE_FORMAT = 13;

// FIRST PASS: Create all the nodes.
print("FIRST PASS" . PHP_EOL);

$row_number = 0;
global $pages;
$pages = [];
$path = [];
while (($row = fgetcsv($data)) !== FALSE) {
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
        print("Skipping empty row $row_number" . PHP_EOL);
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

    print("Processing \"$title\"..." . PHP_EOL);
    $fields = [
        'type' => $type,
        'title' => $title,
        'uid' => 1,
        'path' => [
            'pathauto' => PathautoState::CREATE,
        ],
    ];

    // Create the node.
    if (!empty($row[URL])) {
        $pages[implode('/', $path)] = [
            'nid' => NULL,
            'title' => $title,
            'path' => $path,
            'menu_item' => NULL,
            'mega_menu' => !empty($row[MEGA_MENU]) ? $row_number : NULL,
            'uri' => $row[URL],
        ];
        print("  No content: " . implode(' => ', $path) . PHP_EOL);
    }
    else if (!empty($type)) {
        $node = Drupal::entityTypeManager()
            ->getStorage('node')
            ->create($fields);
        $node->setPublished(TRUE);
        $node->save();
        $pages[implode('/', $path)] = [
            'nid' => $node->id(),
            'title' => $title,
            'path' => $path,
            'menu_item' => NULL,
            'mega_menu' => !empty($row[MEGA_MENU]) ? $row_number : NULL,
            'uri' => NULL,
        ];
        print("  Created $type: " . implode(' => ', $path) . PHP_EOL);
    }
    else {
        print("  Ignoring" . PHP_EOL);
    }
}
fclose($data);

// SECOND PASS: Create the menu hierarchy
print("SECOND PASS\n");

function createMenuEntry($path, $page, &$pages, $menu_name) {
    if (!empty($page['menu_item'])) {
        print("  Menu found. Skipping" . PHP_EOL);
        return;
    }
    $menu_link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');

    // Add path alias to /front if this is the home page and exit early.
    $title = $page['title'];
    if (0 === strcasecmp($title, 'Home')) {
        PathAlias::create([
            'path' => "/node/{$page['nid']}",
            'alias' => '/front',
            'langcode' => 'en',
        ])->save();
        print("  Home page found. Setting it to front page and skipping" . PHP_EOL);
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
            print("  Could not find parent node \"$parent\"" . PHP_EOL);
        }
        else if (empty($page_parent['menu_item'])) {
            print("  Could not find menu for \"$parent\"" . PHP_EOL);
        }
        else {
            $menu_item_parent = $page_parent['menu_item'];
            print("  Parent menu for \"$parent\": $menu_item_parent" . PHP_EOL);
            break;
        }
        $parent_path = array_slice($parent_path, 0, -1);
    }

    // Setup the proper URI for this menu entry.
    if (!empty($page['nid'])) {
        $link = ['uri' => "entity:node/{$page['nid']}"];
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
    print("  Menu for \"$title\": {$pages[$path]['menu_item']}" . PHP_EOL);
}

foreach ($pages as $path => &$page) {
    print("Processing \"{$page['title']}\"..." . PHP_EOL);

    // Insert node in the navigation menu.
    if (!empty($page['mega_menu'])) {
        createMenuEntry($path, $page, $pages, 'main');
    }
}
