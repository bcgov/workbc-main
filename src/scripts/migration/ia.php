<?php

/**
 * Generate nodes for all content type in the WorkBC Refresh IA.
 *
 * Usage: drush scr /scripts/migration/ia -- /path/to/ia.csv
 *
 * Revert:
 * - drush entity:delete node --bundle=page
 * - drush entity:delete node --bundle=landing_page
 * - drush entity:delete menu_link_content
 */

use Drupal\path_alias\Entity\PathAlias;
use Drupal\pathauto\PathautoState;

$file = array_key_exists(0, $extra) ? $extra[0] : __DIR__ . '/ia.csv';
if (empty($file) or ($handle = fopen($file, "r")) === FALSE) {
    die("[WorkBC Migration] Could not open $file\nUsage: drush scr ia.php -- /path/to/ia.csv\n");
}
print("Importing $file\n");

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
print("Creating content...\n");
$row = 0;
$nodes = [];
$path = [];
while (($data = fgetcsv($handle)) !== FALSE) {
    // Skip first header row.
    $row++;
    if ($row < 2) continue;

    // Detect page title and path to create the hierarchy.
    // We build up the $path array to contain the current hierarchy, discarding "Home".
    $title = NULL;
    $level = NULL;
    for ($c = TREE_LAST; $c >= TREE_FIRST; $c--) {
        if (!empty($data[$c])) {
            $title = trim($data[$c]);
            $level = max(0, $c - TREE_FIRST - 1);
            $path[$level] = $title;
            if ($level < count($path)) {
                $path = array_slice($path, 0, $level + 1);
            }
            break;
        }
    }
    if (empty($title)) {
        print("Skipping empty page at row $row\n");
        continue;
    }

    // Detect a type that we can import.
    $t = strtolower($data[DRUPAL_TYPE]);
    if (empty($t) || !array_key_exists($t, $types)) {
        if (!empty($data[MEGA_MENU]) && empty($data[URL])) {
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

    // TODO Add more fields from GatherContent.
    $fields = [
        'type' => $type,
        'title' => $title,
        'uid' => 1,
        'path' => [
            'pathauto' => PathautoState::CREATE,
        ],
    ];

    // Create the node.
    if (!empty($data[URL])) {
        $nodes[implode('/', $path)] = [
            'id' => NULL,
            'title' => $title,
            'path' => $path,
            'menu_item' => NULL,
            'mega_menu' => !empty($data[MEGA_MENU]) ? $row : NULL,
            'uri' => $data[URL]
        ];
        print("No content: " . implode(' => ', $path) . "\n");
    }
    else if (!empty($type)) {
        $node = Drupal::entityTypeManager()
            ->getStorage('node')
            ->create($fields);
        $node->save();
        $nodes[implode('/', $path)] = [
            'id' => $node->id(),
            'title' => $title,
            'path' => $path,
            'menu_item' => NULL,
            'mega_menu' => !empty($data[MEGA_MENU]) ? $row : NULL,
            'uri' => NULL
        ];
        print("Creating $type: " . implode(' => ', $path) . "\n");
    }
    else {
        print("Ignoring: " . implode(' => ', $path) . "\n");
    }
}
fclose($handle);

// SECOND PASS: Create the menu hierarchy.
print("Creating menu...\n");

function createMenuEntry($path, $node, &$nodes, $menu_link_storage, $menu_name) {
    $title = end($node['path']);
    print("Menu for \"$title\"\n");
    if (empty($node)) {
        print("  Could not find node. Skipping\n");
        return;
    }
    if (!empty($node['menu_item'])) {
        print("  Menu found. Skipping\n");
        return;
    }

    // Add path alias to /front if this is the home page and exit early.
    if (0 === strcasecmp($title, 'Home')) {
        PathAlias::create([
            'path' => "/node/{$node['id']}",
            'alias' => '/front',
            'langcode' => 'en',
        ])->save();
        print("  Home page found. Set it to front page and skipping\n");
        return;
    }

    // Find the parent menu item under which this one will be placed.
    // This is not necessarily the immediate parent in the IA tree - it can be an ancestor.
    $menu_item_parent = NULL;
    $parent_path = array_slice($node['path'], 0, -1);
    while (!empty($parent_path)) {
        $parent = implode('/', $parent_path);
        $node_parent = &$nodes[$parent];
        if (empty($node_parent)) {
            print("  Could not find parent node \"$parent\"\n");
        }
        else if (empty($node_parent['menu_item'])) {
            print("  Could not find menu for \"$parent\"\n");
        }
        else {
            $menu_item_parent = $node_parent['menu_item'];
            print("  Parent menu for \"$parent\": $menu_item_parent\n");
            break;
        }
        $parent_path = array_slice($parent_path, 0, -1);
    }

    // Setup the proper URI for this menu entry.
    if (!empty($node['id'])) {
        $link = ['uri' => "entity:node/{$node['id']}"];
    }
    else if (strpos($node['uri'] , 'http') === 0) {
        $link = [
            'uri' => "{$node['uri']}",
            'options' => [
                'attributes' => [
                    'rel' => 'noopener noreferrer',
                    'target' => '_blank',
                ]
            ]
        ];
    }
    else {
        $link = ['uri' => "internal:{$node['uri']}"];
    }
    $menu_link = $menu_link_storage->create([
        'title' => $title,
        'link' => $link,
        'menu_name' => $menu_name,
        'parent' => $menu_item_parent,
        'expanded' => TRUE,
        'weight' => $node['mega_menu']
    ]);
    $menu_link->save();
    $nodes[$path]['menu_item'] = $menu_link->getPluginId();
    print("  Menu for \"$title\": {$nodes[$path]['menu_item']}\n");
}

$menu_link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
foreach ($nodes as $path => &$node) {
    if (!empty($node['mega_menu'])) {
        createMenuEntry($path, $node, $nodes, $menu_link_storage, 'main');
    }
}
