<?php

namespace Drupal\workbc_menu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Drupal\Core\Menu\MenuLinkBase;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Node\NodeInterface;

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
        "<span tabindex=\"0\" class=\"" . implode(' ', $a_classes) . "\">$name</span>" :
        "<a " . implode(' ', $a_attributes) . " class=\"" . implode(' ', $a_classes) . "\" href=\"$url\">$name</a>";
    }
    else {
      $blurb = "";
      $uo = $link->getUrlObject();
      if ($uo->isRouted() && $uo->getRouteName() === 'entity.node.canonical') {
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($uo->getRouteParameters()['node']);
        $blurb = $this->getBlurb($node);
      }
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

  private function getBlurb(NodeInterface $node) {
    $text = '';
    if ($node->hasField('field_navigation_blurb') && !empty($node->get('field_navigation_blurb')->value)) {
      $text = strip_tags($node->get('field_navigation_blurb')->value);
    }
    else if ($node->hasField('field_related_topics_blurb') && !empty($node->get('field_related_topics_blurb')->value)) {
      $text = strip_tags($node->get('field_related_topics_blurb')->value);
    }
    else if ($node->hasField('field_hero_text') && !empty($node->get('field_hero_text')->value)) {
      $text = strip_tags($node->get('field_hero_text')->value);
    }
    else if ($node->hasField('body')) {
      if (!empty($node->get('body')->summary)){
        $text = $node->get('body')->summary;
      }
      else if (!empty($node->get('body')->value)) {
        $text = strip_tags($node->get('body')->value);
      }
    }
    return \Drupal\Component\Utility\Unicode::truncate($text, 125, TRUE, TRUE);
  }

  private function generateMegaMenu($input) {
    /** @var MenuLinkTreeElement[] $input */
    $output = "<ul class=\"nav-t1\">\n";
    foreach ($this->getEnabledItems($input) as $item) {
      $li_classes = ["nav-item"];
      if ($item->hasChildren) {
        array_push($li_classes, "has-submenu");
      }
      $output .= "<li class=\"" . implode(' ', $li_classes) . "\">\n";
      $output .= $this->renderLink($item->link, $item->hasChildren, 1) . "\n";
      if ($item->hasChildren) {
        $output .= "<div class=\"submenu-container\"><div class=\"row g-0 submenu\">\n";

        $children = $this->getEnabledItems($item->subtree);
        $column1 = array_slice($children, 0, 3);
        $output .= "<div class=\"col-sm-4\">\n";
        $output .= "<ul class=\"nav-t2\">\n";
        foreach ($column1 as $child) {
          $output .= "<li class=\"nav-item\">\n";
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
            $output .= "<li class=\"nav-item\">\n";
            $output .= $this->renderLink($child->link, false, 2) . "\n";
            $output .= "</li>\n";
          }
          $output .= "</ul>\n";
        }
        $output .= "</div>\n";

        $output .= "<div class=\"col-sm-4 megamenu-splash\">\n";
        $output .= $item->link->getEntity()->get('field_splash')->value;
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
