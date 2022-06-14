<?php

/**
 * Usage: drush scr /path/to/migrate.php -- /path/to/migration.csv
 */
$file = array_key_exists(0, $extra) ? $extra[0] : __DIR__ . '/migration.csv';
if (empty($file) or ($handle = fopen($file, "r")) === FALSE) {
    die("[WorkBC Migration] Could not open $file\nUsage: drush scr migrate -- /path/to/migration.csv\n");
}
print("Importing $file\n");

// Find the menu where we will insert the content.
global $menu_link_storage, $menu_name, $menu_item_home;
$menu_link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$menu_name = 'main';
$menu_item_home = 'standard.front_page';

// Types that we can import.
// TODO Fill this.
$types = [
    'Basic page' => 'page',
];

// Map of nodes that we created.
// title => [id, parent title]
// TODO Save this in SQLite.
global $nodes;
$nodes = [
];

// FIRST PASS: Create all the nodes.
print("Creating content...\n");
$row = 0;
while (($data = fgetcsv($handle)) !== FALSE) {
    // Skip first 2 rows.
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
                if (empty($parent)) {
                    $parent = NULL;
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

    $node = Drupal::entityTypeManager()
        ->getStorage('node')
        ->create($fields);
    $node->save();
    $nodes[$title] = [
        'id' => $node->id(),
        'parent' => $parent,
        'menu_id' => NULL,
    ];
}
fclose($handle);

// SECOND PASS: Create the menu hierarchy.
print("Creating menu...\n");
foreach ($nodes as $title => $node) {
    $menu_link = $menu_link_storage->create([
        'title' => $title,
        'link' => ['uri' => "internal:/node/{$node['id']}"],
        'menu_name' => $menu_name,
        'parent' => $menu_item_home,
        'expanded' => TRUE,
        'weight' => 0,
    ]);
    $menu_link->save();
    $nodes[$title]['menu_id'] = $menu_link->id();
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
