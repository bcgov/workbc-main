<?php

use Drupal\path_alias\Entity\PathAlias;

/**
 * Usage: drush scr /path/to/migrate.php -- /path/to/migration.csv
 */
$file = array_key_exists(0, $extra) ? $extra[0] : __DIR__ . '/migration.csv';
if (empty($file) or ($handle = fopen($file, "r")) === FALSE) {
    die("[WorkBC Migration] Could not open $file\nUsage: drush scr migrate -- /path/to/migration.csv\n");
}
print("Importing $file\n");

// Find the menu where we will insert the content.
$menu_link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$menu_name = 'main';
$menu_item_home = 'standard.front_page';

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
// TODO Fill this.
$types = [
    'Basic page' => 'page',
];

// Map of nodes that we created.
// title => [id, parent title]
// TODO Save this in SQLite.
$nodes = [
];

// FIRST PASS: Create all the nodes.
print("Creating content...\n");
$row = 0;
while (($data = fgetcsv($handle)) !== FALSE) {
    // Skip first 2 header rows.
    $row++;
    if ($row < 3) continue;

    // Detect a type that we can import.
    $type = $data[9];
    if (!$type || !array_key_exists($type, $types)) {
        // TODO Log warning.
    }

    // Detect title and parent's title to create the hierarchy.
    $parent = NULL;
    $title = NULL;
    for ($c = 4; $c >= 0; $c--) {
        if (!empty($data[$c])) {
            $title = $data[$c];
            if ($c > 0) {
                $parent = $data[$c-1];
                if ($parent === $title) {
                    $parent = $c > 1 ? $data[$c-2] : NULL;
                }
            }
            print("$title => $parent\n");
            break;
        }
    }
    if (empty($title)) {
        // TODO Log warning.
        print("Skipping empty page at row $row\n");
        continue;
    }

    // TODO Detect the type from the sheet.
    $type = 'page';

    // TODO Add more fields from GatherContent.
    $fields = [
        'type' => $type,
        'title' => $title,
        'uid' => 1,
    ];

    // Create the node.
    $node = Drupal::entityTypeManager()
        ->getStorage('node')
        ->create($fields);
    $node->save();
    $nodes[$title] = [
        'id' => $node->id(),
        'parent' => $parent,
        'menu_item' => NULL,
    ];
}
fclose($handle);

// SECOND PASS: Create the menu hierarchy.
print("Creating menu...\n");

function createMenuEntry($title, $node, &$nodes, $menu_link_storage, $menu_name, $menu_item_home) {
    print("Menu for \"$title\"\n");
    if (empty($node)) {
        print("  Could not find node \"$title\". Skipping\n");
        return;
    }
    if (!empty($node['menu_item'])) {
        print("  Menu found. Skipping\n");
        return;
    }

    $menu_item_parent = $menu_item_home;
    if (!empty($node['parent'])) {
        $node_parent = &$nodes[$node['parent']];
        if (empty($node_parent)) {
            print("  Could not find parent node \"{$node['parent']}\"\n");
        }
        else {
            if (empty($node_parent['menu_item'])) {
                print("  Could not find menu for \"{$node['parent']}\". Creating it...\n");
                createMenuEntry($node['parent'], $node_parent, $nodes, $menu_link_storage, $menu_name, $menu_item_home);
            }
            if (empty($node_parent['menu_item'])) {
                print("  Could not find menu for \"{$node['parent']}\" after creation\n");
            }
            else {
                $menu_item_parent = $node_parent['menu_item'];
                print("  Parent menu for \"{$node['parent']}\": $menu_item_parent\n");
            }
        }
    }

    $menu_link = $menu_link_storage->create([
        'title' => $title,
        'link' => ['uri' => "entity:node/{$node['id']}"],
        'menu_name' => $menu_name,
        'parent' => $menu_item_parent,
        'expanded' => TRUE,
        'weight' => 0,
    ]);
    $menu_link->save();
    $nodes[$title]['menu_item'] = $menu_link->getPluginId();

    // Add path alias to /front if this is the home page.
    if (0 === strcasecmp($title, 'Home')) {
        PathAlias::create([
            'path' => "/node/{$node['id']}",
            'alias' => '/front',
            'langcode' => 'en',
        ])->save();
    }

    print("  Menu for \"$title\": {$nodes[$title]['menu_item']}\n");
}

foreach ($nodes as $title => &$node) {
    createMenuEntry($title, $node, $nodes, $menu_link_storage, $menu_name, $menu_item_home);
}

/**
 * Access GatherContent item.
 */
// $email = 'TODO';
// $apiKey = 'TODO';
// $projectId = 284269;
// $client = new \GuzzleHttp\Client();
// $gc = new \Cheppers\GatherContent\GatherContentClient($client);
// $gc
//   ->setEmail($email)
//   ->setApiKey($apiKey);
// try {
//     $item = $gc->itemGet(12593020);
//     $template = $gc->templateGet($item->templateId);
//     $component = $gc->componentGet('b9abb081-fb11-444a-96bb-04777aefc84a');
// }
// catch (\Exception $e) {
//     echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
//     exit(1);
// }
// print_r($item->content);

/**
 * Create paragraph
 * https://www.drupal.org/project/paragraphs/issues/2707017
 */

?>
