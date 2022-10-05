<?php

use Drupal\path_alias\Entity\PathAlias;
use Drupal\pathauto\PathautoState;

/**
 * Generate nodes for all content types in the WorkBC IA.
 *
 * Prerequisites:
 * - drush scr scripts/migration/taxonomy -- -v content_groups /var/www/html/scripts/migration/data/content_groups.csv
 *
 * Usage:
 * - drush scr scripts/migration/ia
 *
 * Revert:
 * - drush entity:delete node --bundle=page
 * - drush entity:delete node --bundle=labour_market_monthly
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
    'landing page' => 'page',
    'labour market monthly' => 'labour_market_monthly',
];

// Content groups for editing permissions.
$content_groups = [];
foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('content_groups') as $term) {
    $content_groups[strtolower($term->name)] = $term->tid;
}

// The columns we are interested in.
const TREE_FIRST = 0;
const TREE_LAST = 5;
const MEGA_MENU = 6;
const DRUPAL_TYPE = 7;
const LEGACY_URL = 11;
const URL = 12;
const PAGE_FORMAT = 13;
const CONTENT_GROUP = 14;

// FIRST PASS: Create all the nodes.
print("FIRST PASS =================" . PHP_EOL);

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
    $row_type = strtolower($row[DRUPAL_TYPE]);
    if (empty($row_type) || !array_key_exists($row_type, $types)) {
        if (!empty($row[MEGA_MENU]) && empty($row[URL])) {
            // Create a placeholder page until we have a better way to deal with this entry.
            $type = 'page';
        }
        else {
            $type = NULL;
        }
    }
    else {
        $type = $types[$row_type];
    }

    // Populate the standard fields.
    print("Processing \"$title\"..." . PHP_EOL);
    $fields = [
        'type' => $type,
        'title' => $title,
        'uid' => 1,
        'path' => [
            'pathauto' => PathautoState::CREATE,
        ],
        'moderation_state' => 'published',
    ];

    // Legacy URL.
    if (stripos($row[LEGACY_URL], 'https://www.workbc.ca', 0) === 0) {
        $fields['field_legacy_url'] = [
            'title' => $title,
            'uri' => $row[LEGACY_URL],
        ];
    }

    // Page format.
    switch (strtolower($row[PAGE_FORMAT])) {
        case 'sidenav':
            $fields['field_page_format'] = 'sidenav';
        break;
        case 'standard':
            $fields['field_page_format'] = 'standard';
        break;
        case 'wide':
            $fields['field_page_format'] = 'wide';
        break;
        default:
            if ($row_type === 'landing page') {
                $fields['field_page_format'] = 'wide';
            }
            else {
                $fields['field_page_format'] = 'standard';
            }
        break;
    }

    // Content group.
    $content_group = strtolower($row[CONTENT_GROUP]);
    if (array_key_exists($content_group, $content_groups)) {
        $fields['field_content_group'] = ['target_id' => $content_groups[$content_group]];
    }
    else {
        $fields['field_content_group'] = ['target_id' => $content_groups['workbc']];
    }

    // Process the IA item.
    // If an explicit URL is given, we skip node creation and move directly to menu item insertion.
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
        // We want to create or update a Drupal node for this IA item.
        // Identifying an existing node by title is not enough because some pages have non-unique titles.
        // Instead, we identify the node by its position in the navigation menu:
        // 1. Identify the parent menu item in the navigation menu
        // 2. Identify the node menu item in the navigation menu
        // 3. Retrieve the node entity from the menu item
        // Fall back to re-creating the node if any step above fails.
        $node = NULL;
        if (count($path) >= 2) {
            $parent = implode('/', array_slice($path, 0, count($path)-1));
            if (!array_key_exists($parent, $pages)) {
                print("  Could not find parent \"$parent\" in recently processed pages" . PHP_EOL);
            }
            else {
                $menu_items_parent = \Drupal::entityTypeManager()
                    ->getStorage('menu_link_content')
                    ->loadByProperties([
                        'link.uri' => 'entity:node/' . $pages[$parent]['nid'],
                        'menu_name' => 'main',
                    ]);
                $node = NULL;
                if (empty($menu_items_parent)) {
                    print("  Could not find parent menu item \"$parent\"" . PHP_EOL);
                }
                else if (count($menu_items_parent) > 1) {
                    print("  Found multiple parent menu items \"$parent\"" . PHP_EOL);
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
                        print("  Could not find menu item whose parent is \"$parent\"" . PHP_EOL);
                    }
                    else {
                        $nid = (int) filter_var(current($menu_items_page)->link->uri, FILTER_SANITIZE_NUMBER_INT);
                        $node = Drupal::entityTypeManager()
                            ->getStorage('node')
                            ->load($nid);
                    }
                }
            }
        }
        else {
            // No parent: Get the menu item directly and hope the title is unique.
            $menu_items_page = \Drupal::entityTypeManager()
                ->getStorage('menu_link_content')
                ->loadByProperties([
                    'menu_name' => 'main',
                    'title' => $title
                ]);
            if (empty($menu_items_page)) {
                print("  Could not find menu item" . PHP_EOL);
            }
            else {
                $nid = (int) filter_var(current($menu_items_page)->link->uri, FILTER_SANITIZE_NUMBER_INT);
                $node = Drupal::entityTypeManager()
                    ->getStorage('node')
                    ->load($nid);
            }
        }
        if (empty($node)) {
            print("  Creating new node for \"$title\"" . PHP_EOL);
            $node = Drupal::entityTypeManager()
                ->getStorage('node')
                ->create($fields);
        }
        else {
            foreach ($fields as $field => $value) {
                $node->$field = $value;
            }
        }
        $node->setPublished(TRUE);
        $node->save();

        $pages[implode('/', $path)] = [
            'nid' => $node->id(),
            'title' => $title,
            'path' => $path,
            'menu_item' => NULL,
            'mega_menu' => strcasecmp($row[MEGA_MENU], "yes") === 0 ? $row_number : false,
            'uri' => NULL,
        ];
        print("  Created/updated $type: " . implode(' => ', $path) . PHP_EOL);
    }
    else {
        print("  No explicit URL and no detected type. Ignoring" . PHP_EOL);
    }
}
fclose($data);

// SECOND PASS: Create the menu hierarchy
print("SECOND PASS =================" . PHP_EOL);

function createMenuEntry($path, $page, &$pages, $menu_name) {
    if (!empty($page['menu_item'])) {
        print("  Menu found. Skipping" . PHP_EOL);
        return;
    }

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
    $menu_link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
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
        'weight' => $page['mega_menu'] || 0,
        'enabled' => !!$page['mega_menu']
    ]);
    $menu_link->save();
    $pages[$path]['menu_item'] = $menu_link->getPluginId();
    print("  Menu for \"$title\": {$pages[$path]['menu_item']}" . PHP_EOL);
}

// Delete existing main menu items and process new items.
\Drupal::service('plugin.manager.menu.link')->deleteLinksInMenu('main');
foreach ($pages as $path => &$page) {
    print("Processing \"{$page['title']}\"..." . PHP_EOL);
    createMenuEntry($path, $page, $pages, 'main');
}
