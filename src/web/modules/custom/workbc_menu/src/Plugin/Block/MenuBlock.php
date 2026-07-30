<?php

namespace Drupal\workbc_menu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Menu\MenuLinkBase;
use Drupal\Core\Menu\MenuLinkTreeElement;

/**
 * Provides a WorkBC Menu block.
 *
 * @Block(
 *   id = "menu_block",
 *   admin_label = @Translation("WorkBC Menu block"),
 *   category = @Translation("WorkBC"),
 * )
 */
class MenuBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => $this->generateMegaMenu(\Drupal::menuTree()->load('main', new \Drupal\Core\Menu\MenuTreeParameters())),
    ];
  }

  private function renderLink(MenuLinkBase $link, bool $hasChildren, int $level) {
    $name = $link->getTitle();
    $url = $link->getUrlObject()->toString();
    $a_classes = ["nav-link"];
    $a_attributes = [];
    if ($hasChildren) {
      array_push($a_classes, "has-submenu");
    }
    if (array_key_exists('attributes', $link->getOptions())) foreach ($link->getOptions()['attributes'] as $key => $attr) {
      $a_attributes[] = "$key=\"$attr\"";
    }
    if ($level === 1) {
      return $url !== "/" ?
        "<span class=\"" . implode(' ', $a_classes) . "\">$name</span>" :
        "<a " . implode(' ', $a_attributes) . " class=\"" . implode(' ', $a_classes) . "\" href=\"$url\">$name</a>";
    }
    else {
      $blurb = "";
      $uo = $link->getUrlObject();
      if ($uo->isRouted() && $uo->getRouteName() === 'entity.node.canonical') {
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($uo->getRouteParameters()['node']);
      }
      else {
        $node = null;
      }
      $blurb = $this->getBlurb($link, $node);
      $attributes = implode(' ', $a_attributes);
      $classes = implode(' ', $a_classes);
      return <<<EOT
        <a $attributes class="$classes" href="$url">
          <span class="nav-title">$name</span>
          <span class="nav-blurb">$blurb</span>
        </a>
      EOT;
    }
  }

  private function getBlurb($link, $node) {
    if ($link->getEntity()->hasField('field_splash') && !empty($link->getEntity()->get('field_splash')->value)) {
      return strip_tags($link->getEntity()->get('field_splash')->value);
    }
    else if (!empty($node) && $node->hasField('field_navigation_blurb') && !empty($node->get('field_navigation_blurb')->value)) {
      return $node->get('field_navigation_blurb')->value;
    }
    return '';
  }

  private function generateMegaMenu($input) {
    /** @var MenuLinkTreeElement[] $input */
    $output = "<ul class=\"nav-t1\">\n";
    foreach ($this->getEnabledItems($input) as $item) {
      $li_classes = ["nav-item"];
      if ($item->hasChildren) {
        array_push($li_classes, "has-submenu");
      }
      $output .= "<li tabindex=\"0\" " . ($item->hasChildren ? "aria-expanded=\"false\" " : "") . "aria-role=\"menuitem\" class=\"" . implode(' ', $li_classes) . "\">\n";
      $output .= $this->renderLink($item->link, $item->hasChildren, 1) . "\n";
      if ($item->hasChildren) {
        $output .= "<div class=\"submenu-container\"><div class=\"row g-0 submenu\">\n";

        $children = $this->getEnabledItems($item->subtree);
        $column1 = array_slice($children, 0, 3);
        $output .= "<div class=\"col-sm-4\">\n";
        $output .= "<ul class=\"nav-t2\">\n";
        foreach ($column1 as $child) {
          $output .= "<li aria-role=\"menuitem\" class=\"nav-item\">\n";
          $output .= $this->renderLink($child->link, $child->hasChildren, 2) . "\n";
          $output .= "</li>\n";
        }
        $output .= "</ul>\n";
        $output .= "</div>\n";

        $column2 = array_slice($children, 3);
        $output .= "<div class=\"col-sm-4\">\n";
        if (count($column2) > 0) {
          $output .= "<ul class=\"nav-t2\">\n";
          foreach ($column2 as $child) {
            $output .= "<li aria-role=\"menuitem\" class=\"nav-item\">\n";
            $output .= $this->renderLink($child->link, false, 2) . "\n";
            $output .= "</li>\n";
          }
          $output .= "</ul>\n";
        }
        $rendered = "";
        if ($item->link->getEntity()->get('field_splash')->value) {
          $build = [
            '#type' => 'processed_text',
            '#text' => $item->link->getEntity()->get('field_splash')->value,
            '#format' => 'full_html',
          ];
          $rendered = \Drupal::service('renderer')->renderInIsolation($build);
        }
        $output .= "</div>\n";
        $output .= "<div class=\"col-sm-4 megamenu-splash\">\n";
        $output .= $rendered;
        $output .= "</div></div></div>\n";
      }
      $output .= "</li>\n";
    }
    $output .= "</ul>\n";
    return $output;
  }

  private function getEnabledItems($input) {
    /** @var MenuLinkTreeElement[] $input */
    $enabled = array_filter($input, function($item) {
      return $item->link->isEnabled();
    });
    uasort($enabled, function($a, $b) {
      $w1 = $a->link->getWeight();
      $w2 = $b->link->getWeight();
      if ($w1 == $w2) return 0;
      return $w1 < $w2 ? -1 : 1;
    });
    return $enabled;
  }
}
