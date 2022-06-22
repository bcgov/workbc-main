<?php

// Recreate the navmenu.html structure
// Usage: drush scr web/experiments/navmenuhtml/navmenu.php

function generateMenuTree($input, $level = 1) {
  $indent = str_repeat(' ', $level * 4);
  $ul_classes = ["navbar-nav", "nav-t$level"];
  switch ($level) {
    case 2: array_push($ul_classes, "dropdown-menu"); break;
    case 3: array_push($ul_classes, "dropdown-menu", "submenu"); break;
  }
  $output = "$indent<ul class=\"" . implode(' ', $ul_classes) . "\">\n";
  foreach ($input as $key => $item) {
    if ($item->link->isEnabled()) {
      $li_classes = ["nav-item"];
      if ($item->hasChildren) {
        array_push($li_classes, "dropdown");
      }
      $output .= "$indent  <li class=\"" . implode(' ', $li_classes) . "\">\n";
      $name = $item->link->getTitle();
      $url = $item->link->getUrlObject()->toString();
      $a_classes = ["nav-link"];
      if ($item->hasChildren) {
        array_push($a_classes, "dropdown-toggle");
      }
      $output .= "$indent    <a class=\"" . implode(' ', $a_classes) . "\" href=\"$url\">$name</a>\n";
      if ($item->hasChildren) {
        if ($level === 1) {
          $output .= "$indent    <div class=\"submenu-container\"><div class=\"row g-0 submenu\"><div class=\"col-sm-8\">\n";
        }
        $output .= generateMenuTree($item->subtree, $level + 1);
        if ($level === 1) {
          $output .= "$indent    </div></div></div>\n";
        }
      }
      $output .= "$indent  </li>\n";
    }
  }
  $output .= "$indent</ul>\n";
  return $output;
}
echo generateMenuTree(\Drupal::menuTree()->load('main', new \Drupal\Core\Menu\MenuTreeParameters()));
