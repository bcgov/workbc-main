<?php

require('utilities.php');

use Drupal\pathauto\PathautoState;

/**
 * Generate nodes for all content types in the WorkBC IA.
 *
 * Usage: drush scr scripts/migration/ia
 */

$file = __DIR__ . '/data/ia.csv';
if (($handle = fopen($file, 'r')) === FALSE) {
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
    'regional profile' => 'region_profile',
    'bc profile' => 'bc_profile'
];

// Content groups for editing permissions.
$content_groups = [];
foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('content_groups') as $term) {
    $content_groups[strtolower($term->name)] = $term->tid;
}

// The columns we are interested in.
const COL_TREE_FIRST = 0;
const COL_TREE_LAST = 5;
const COL_MEGA_MENU = 6;
const COL_DRUPAL_TYPE = 7;
const COL_LEGACY_URL = 11;
const COL_URL = 12;
const COL_PAGE_FORMAT = 13;
const COL_CONTENT_GROUP = 14;
const COL_VIEW_MODE = 15;

// FIRST PASS: Create all the nodes.
print("FIRST PASS =================" . PHP_EOL);

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
    for ($c = COL_TREE_LAST; $c >= COL_TREE_FIRST; $c--) {
        if (!empty($row[$c])) {
            $title = trim($row[$c]);
            $level = max(0, $c - COL_TREE_FIRST - 1);
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
    $row_type = strtolower($row[COL_DRUPAL_TYPE]);
    if (empty($row_type) || !array_key_exists($row_type, $types)) {
        if (!empty($row[COL_MEGA_MENU]) && empty($row[COL_URL])) {
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
        'path' => !empty($row[COL_URL]) ? [
            'alias' => $row[COL_URL],
            'pathauto' => PathautoState::SKIP,
        ] : [
            'pathauto' => PathautoState::SKIP,
        ],
        'moderation_state' => 'published',
    ];

    // View mode.
    if (!empty($row[COL_VIEW_MODE])) {
        $fields['view_mode_selection'][] = [
            'target_id' => 'node.' . $row[COL_VIEW_MODE],
        ];
    }

    // Page format.
    switch (strtolower($row[COL_PAGE_FORMAT])) {
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
    $content_group = strtolower($row[COL_CONTENT_GROUP]);
    if (array_key_exists($content_group, $content_groups)) {
        $fields['field_content_group'] = ['target_id' => $content_groups[$content_group]];
    }
    else {
        $fields['field_content_group'] = ['target_id' => $content_groups['workbc']];
    }

    // Process the IA item.
    if (!empty($type)) {
        $node = createNode($fields, $row[COL_LEGACY_URL]);

        $pages[implode('/', $path)] = [
            'nid' => $node->id(),
            'title' => $title,
            'path' => $path,
            'menu_item' => NULL,
            'mega_menu' => strcasecmp($row[COL_MEGA_MENU], 'yes') === 0 ? $row_number : false,
            'uri' => $row[COL_URL] ?? NULL,
        ];
    }
    // If an explicit URL is given but there is no node type:
    // - Insert a menu link
    // - Setup a redirection
    else if (!empty($row[COL_URL])) {
        $pages[implode('/', $path)] = [
            'nid' => NULL,
            'title' => $title,
            'path' => $path,
            'menu_item' => NULL,
            'mega_menu' => strcasecmp($row[COL_MEGA_MENU], 'yes') === 0 ? $row_number : false,
            'uri' => $row[COL_URL],
        ];

        if (str_starts_with($row[COL_URL], '/')) {
            createRedirection($row[COL_LEGACY_URL], 'internal:' . $row[COL_URL]);
        }

        print("  No content, just menu item: " . implode(' => ', $path) . PHP_EOL);
    }
    else {
        print("  No explicit URL and no detected type. Ignoring" . PHP_EOL);
    }
}
fclose($handle);

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
        print("  Home page found. Skipping" . PHP_EOL);
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
    $menu_link = \Drupal::entityTypeManager()
    ->getStorage('menu_link_content')
    ->create([
        'title' => $title,
        'link' => $link,
        'menu_name' => $menu_name,
        'parent' => $menu_item_parent,
        'expanded' => TRUE,
        'weight' => $page['mega_menu'] ?? 0,
        'enabled' => !!$page['mega_menu']
    ]);
    $menu_link->save();
    $pages[$path]['menu_item'] = $menu_link->getPluginId();
    print("  Menu for \"$title\": {$pages[$path]['menu_item']}" . PHP_EOL);

    // Update the node path (which may be based on the menu path).
    if (!empty($page['nid']) && empty($page['uri'])) {
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($page['nid']);
        $node->path->pathauto = PathautoState::CREATE;
        $node->save();
    }
}

// Delete existing main menu items and process new items.
\Drupal::service('plugin.manager.menu.link')->deleteLinksInMenu('main');
foreach ($pages as $path => &$page) {
    print("Processing \"{$page['title']}\"..." . PHP_EOL);
    createMenuEntry($path, $page, $pages, 'main');
}
