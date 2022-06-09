<?php

/**
 * Usage: drush scr /path/to/migrate.php /path/to/ia.csv
 */

$file = $extra[0];
if (empty($file) or ($handle = fopen($file, "r")) === FALSE) {
    die("[WorkBC Migration] Could not open $file\nUsage: drush scr migrate -- /path/to/ia.csv\n");
}

// Find the menu where we will insert the content.
// https://drupal.stackexchange.com/a/284276/767
$menu_link_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$menu_name = 'main';
$menu = $menu_link_storage->loadByProperties(['menu_name' => $menu_name]);
$menu_item_home = 'standard.front_page';

// Types that we can import.
$types = [
    'Landing page' => 'action',
    'Basic page' => 'page',
    'Career profile' => 'career_profile',
];

// Map of nodes that we created.
// title => id
// TODO Save this in SQLite.
$nodes = [
];

while (($data = fgetcsv($handle)) !== FALSE) {
    // Detect a type that we can import.
    $type = $data[9];
    if (!$type || !array_key_exists($type, $types)) {
        // TODO Log warning.
        continue;
    }

    // Detect title and parent's title to create the hierarchy.
    $parent = NULL;
    $title = NULL;
    for ($c = 5; $c >= 0; $c--) {
        if ($data[$c]) {
            $title = $data[$c];
            if ($c > 0) {
                $parent = $data[$c-1];
                if ($parent === $title) {
                    $parent = NULL;
                }
            }
            break;
        }
    }
    if (!$title) {
        // TODO Log warning.
        continue;
    }

    $fields = [
        'type' => $type,
        'title' => $title,
        'uid' => 1,
    ];

    print("Creating new $type with title: \"$title\" and parent: \"$parent\"\n");
    // Insert the node in the menu hierarchy.
    //
    // $node = Drupal::entityTypeManager()
    //     ->getStorage('node')
    //     ->create($fields);
    // $node->save();
    //
    $nodes[$title] = NULL; // $node->id()
    if ($parent && !array_key_exists($parent, $nodes)) {
        // TODO Log warning.
        print("  Parent node not found: \"$parent\"\n");
    }
}
fclose($handle);

/**
 * CODE SAMPLES
 */

/**
 * Navigate the menu hierarchy.
 */
// $top_level = NULL;
// foreach ($main_menu as $menu_item) {
//   $parent_id = $menu_item->getParentId();
//   if (!empty($parent_id)) {
//     $top_level = $parent_id;
//     break;
//   }
// }

/**
 * Create menu item.
 */
// $menu_link_storage->create([
//     'title' => 'My menu link title',
//     'link' => ['uri' => 'internal:/my/path'],
//     'menu_name' => $menu_name,
//     'parent' => $menu_item_home,
//     'expanded' => TRUE,
//     'weight' => 0,
// ])->save();

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
//     $item = $gc->itemGet(14507644);
//     $template = $gc->templateGet($item->templateId);
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
