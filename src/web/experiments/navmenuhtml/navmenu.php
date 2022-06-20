<?php

// Recreate the navmenu.html structure
// Usage: drush scr web/experiments/navmenuhtml/navmenu.php

function generateSubMenuTree(&$output, $input, $parent = FALSE) {
    $input = array_values($input);
    foreach($input as $key => $item) {
      //If menu element disabled skip this branch
      if ($item->link->isEnabled()) {
        $key = 'submenu-' . $key;
        $name = $item->link->getTitle();
        $url = $item->link->getUrlObject();
        $url_string = $url->toString();

        //If not root element, add as child
        if ($parent === FALSE) {
          $output[$key] = [
            'name' => $name,
            'tid' => $key,
            'url_str' => $url_string
          ];
        } else {
          $parent = 'submenu-' . $parent;
          $output['child'][$key] = [
            'name' => $name,
            'tid' => $key,
            'url_str' => $url_string
          ];
        }

        if ($item->hasChildren) {
          if ($item->depth == 1) {
            generateSubMenuTree($output[$key], $item->subtree, $key);
          } else {
            generateSubMenuTree($output['child'][$key], $item->subtree, $key);
          }
        }
      }
    }
}

$tree = NULL;
generateSubMenuTree($tree, \Drupal::menuTree()->load('main', new \Drupal\Core\Menu\MenuTreeParameters()));
print(json_encode($tree, JSON_PRETTY_PRINT));