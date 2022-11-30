<?php

namespace Drupal\workbc_menu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\image\Entity\ImageStyle;

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

  private function renderLink($link, $hasChildren) {
    $name = $link->getTitle();
    $url = $link->getUrlObject()->toString();
    $a_classes = ["nav-link"];
    $a_attributes = [];
    if($hasChildren) {
      array_push($a_classes, "has-submenu");
    }
    if (array_key_exists('attributes', $link->getOptions())) foreach ($link->getOptions()['attributes'] as $key => $attr) {
      $a_attributes[] = "$key=\"$attr\"";
    }
    return "<a " . implode(' ', $a_attributes) . " class=\"" . implode(' ', $a_classes) . "\" href=\"$url\">$name</a>";
  }

  private function generateMenuTree($input, $level = 1) {
    $indent = str_repeat(' ', $level * 4);
    $ul_classes = ["nav-t$level"];
    $output = "$indent<ul class=\"" . implode(' ', $ul_classes) . "\">\n";
    $enabled = array_filter($input, function($item) {
      return $item->link->isEnabled();
    });
    uasort($enabled, function($a, $b) {
      $w1 = $a->link->getWeight();
      $w2 = $b->link->getWeight();
      if ($w1 == $w2) return 0;
      return $w1 < $w2 ? -1 : 1;
    });
    foreach ($enabled as $item) {
      $li_classes = ["nav-item"];
      if ($item->hasChildren) {
        array_push($li_classes, "has-submenu");
      }
      $output .= "$indent  <li class=\"" . implode(' ', $li_classes) . "\">\n";
      $url = $item->link->getUrlObject()->toString();
      $output .= "$indent    " . $this->renderLink($item->link, $item->hasChildren) . "\n";
      if ($item->hasChildren) {
        if ($level === 1) {
          $output .= "$indent    <div class=\"submenu-container\"><div class=\"row g-0 submenu\"><div class=\"col-sm-8\">\n";
        }
        $output .= $this->generateMenuTree($item->subtree, $level + 1);
        if ($level === 1) {
          $output .= "$indent    </div>\n";

          $params = $item->link->getRouteParameters();
          $node = \Drupal::entityTypeManager()->getStorage('node')->load($params['node']);

          $hero_text = "";
          if (!$node->get('field_hero_text')->isEmpty()) {
            $hero_text = $node->get('field_hero_text')->value;
          }

          $hero_image_url = "";
          if (!$node->get('field_hero_image')->isEmpty()) {
            $image_id = $node->field_hero_image->entity->getFileUri();
            $hero_image_url = ImageStyle::load('megamenu')->buildUrl($image_id);
          }

          $content = <<<EOT
            <div class="col-sm-4 megamenu-splash">
              <img class="megamenu-splash__image" src="$hero_image_url" />
              <div class="megamenu-splash__content">$hero_text</div>
              <div class="megamenu-splash__actions">
                <a class="action-link" href="$url">Read More</a>
              </div>
            </div>
          EOT;
          $output .= $content;
          $output .= "$indent     </div></div>\n";
        }
      }
      $output .= "$indent  </li>\n";
    }
    $output .= "$indent</ul>\n";
    return $output;
  }
}
