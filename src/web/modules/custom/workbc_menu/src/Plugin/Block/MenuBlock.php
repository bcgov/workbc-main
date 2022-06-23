<?php

namespace Drupal\workbc_menu\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a WorkBC Menu Block.
 *
 * @Block(
 *   id = "menu_block",
 *   admin_label = @Translation("WorkBC menu block"),
 *   category = @Translation("WorkBC"),
 * )
 */
class MenuBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => $this->generateMenuTree(\Drupal::menuTree()->load('main', new \Drupal\Core\Menu\MenuTreeParameters())),
    ];
  }

  private function renderLink($link) {
    $name = $link->getTitle();
    $url = $link->getUrlObject()->toString();
    $a_classes = ["nav-link"];
    $a_attributes = [];
    if (array_key_exists('attributes', $link->getOptions())) foreach ($link->getOptions()['attributes'] as $key => $attr) {
      $a_attributes[] = "$key=\"$attr\"";
    }
    return "<a " . implode(' ', $a_attributes) . " class=\"" . implode(' ', $a_classes) . "\" href=\"$url\">$name</a>";
  }

  private function generateMenuTree($input, $level = 1) {
    $indent = str_repeat(' ', $level * 4);
    $ul_classes = ["nav-t$level"];
    $output = "$indent<ul class=\"" . implode(' ', $ul_classes) . "\">\n";
    foreach ($input as $key => $item) {
      if ($item->link->isEnabled()) {
        $li_classes = ["nav-item"];
        if ($item->hasChildren) {
          array_push($li_classes, "has-submenu");
        }
        $output .= "$indent  <li class=\"" . implode(' ', $li_classes) . "\">\n";
        $url = $item->link->getUrlObject()->toString();
        $output .= "$indent    " . $this->renderLink($item->link) . "\n";
        if ($item->hasChildren) {
          if ($level === 1) {
            $output .= "$indent    <div class=\"submenu-container\"><div class=\"row g-0 submenu\"><div class=\"col-sm-8\">\n";
          }
          $output .= $this->generateMenuTree($item->subtree, $level + 1);
          if ($level === 1) {
            $output .= "$indent    </div>\n";
            // TODO Retrieve image and blurb (?) of the current $item node.
            $content = <<<EOT
              <div class="col-sm-4 nav-t1-splash">
                <img src="https://picsum.photos/300/200" />
                <p>Discover job application tups and search the WorkBC job board.</p>
                <a href="$url">Read More</a>
              </div>
            EOT;
            $output .= $content;
            $output .= "$indent     </div></div>\n";
          }
        }
        $output .= "$indent  </li>\n";
      }
    }
    $output .= "$indent</ul>\n";
    return $output;
  }
}
