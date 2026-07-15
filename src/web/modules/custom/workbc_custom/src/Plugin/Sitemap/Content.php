<?php

namespace Drupal\workbc_custom\Plugin\Sitemap;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sitemap\Attribute\Sitemap;
use Drupal\sitemap\SitemapBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

if (!function_exists('array_find')) {
  function array_find(array $array, callable $callback): mixed
  {
      foreach ($array as $key => $value) {
          if ($callback($value, $key)) {
              return $value;
          }
      }

      return null;
  }
}

/**
 * Provides a sitemap for an individual content item.
 */
#[Sitemap(
  id: 'content',
  title: new TranslatableMarkup('Content'),
  description: new TranslatableMarkup('All WorkBC content.'),
  enabled: FALSE,
  settings: [
  ]
)]
class Content extends SitemapBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view() {
    // Load the main menu.
    $menu_weight = [
      'main' => 0,
      'account' => 5000, // push account items after main
      'footer' => 6000, // push footer items after account
    ];
    $menu_items = array_reduce(\Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties([
      'menu_name' => ['main', 'footer', 'account'],
      'enabled' => 1,
    ]), function ($menu_items, $menu_item) use($menu_weight) {
      $entity = $menu_item->link->getEntity();
      $menu_items["menu_link_content:{$entity->uuid()}"] = [
        'title' => $entity->get('title')->value,
        'parent' => $entity->get('parent')->value,
        'weight' => $entity->get('weight')->value + $menu_weight[$entity->get('menu_name')->value],
        'url' => $entity->getUrlObject()->toString()
      ];
      return $menu_items;
    }, []);
    foreach ($menu_items as $menu_item) {
      if (!empty($menu_item['parent']) && isset($menu_items[$menu_item['parent']]) && empty($menu_items[$menu_item['parent']]['url'])) {
        $parts = array_filter(explode('/', parse_url($menu_item['url'], PHP_URL_PATH)));
        $menu_items[$menu_item['parent']]['url'] = '/' . join('/', array_slice($parts, 0, -1));
      }
    }

    // Query the content in the order we want to display.
    $nids0 = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('type', 'page')
      ->condition('title', 'Home')
      ->execute();
    $nids1 = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('type', 'page')
      ->sort('title')
      ->execute();
    $nids2 = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('type', ['career_profile', 'industry_profile', 'region_profile', 'bc_profile', 'workbc_centre'], 'IN')
      ->sort('title')
      ->execute();
    $nids3 = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('type', ['blog', 'news', 'success_story'], 'IN')
      ->sort('field_published_date', 'DESC')
      ->execute();

    // Group URLs by directory.
    $structure = [];
    foreach (array_merge($nids0, $nids1, $nids2, $nids3) as $nid) {
      $node = \Drupal\node\Entity\Node::load($nid);
      $url = \Drupal::service('path_alias.manager')->getAliasByPath("/node/{$nid}");
      $menu_item = array_find($menu_items, function($menu_item) use($url) {
        return $menu_item['url'] == $url;
      });
      $parts = array_values(array_filter(explode('/', parse_url($url, PHP_URL_PATH))));

      // Place content types in their correct IA location depending on their type.
      switch ($node->getType()) {
        case 'career_profile':
          $parts = ['plan-career', 'career-profiles', end($parts)];
          break;
        case 'industry_profile':
          $parts = ['labour-market', 'bcs-industries-and-sectors', 'industry-profiles', end($parts)];
          break;
        case 'region_profile':
        case 'bc_profile':
          $parts = ['labour-market', 'bc-and-regional-profiles', end($parts)];
          break;
        case 'workbc_centre':
          $parts = ['get-support', 'workbc-centre-services', end($parts)];
          break;
        case 'blog':
        case 'news':
        case 'success_story':
          $parts = ['quick-reads', end($parts)];
          break;
        default:
          break;
      }
      $current = &$structure;
      foreach ($parts as $index => $part) {
        if (!isset($current[$part])) {
          $parent_url = '/' . join('/', array_slice($parts, 0, $index+1));
          $parent_item = array_find($menu_items, function($menu_item) use($parent_url) {
            return $menu_item['url'] == $parent_url;
          });
          $current[$part] = [
            '#metadata' => [
              'title' => $parent_item ? $parent_item['title'] : $part,
              'weight' => $parent_item ? $parent_item['weight'] : PHP_INT_MAX
            ]
          ];
        }
        $current = &$current[$part];
      }
      $current['#metadata'] = [
        'title' => $node->getTitle(),
        'url' => $url,
        'weight' => $url == "/front" ? -1000 : ($menu_item ? intval($menu_item['weight']) : 1000)
      ];
    }

    // Add glossary manually.
    $glossary_item = array_find($menu_items, function($menu_item) {
      return $menu_item['url'] == "/glossary";
    });
    if ($glossary_item) {
      $structure['glossary']['#metadata'] = $glossary_item;
    }

    // Render into an item_list tree structure.
    $items = [];
    function renderItemList($structure, &$items) {
      uasort($structure, function($a, $b) {
        if (!isset($a['#metadata'])) return PHP_INT_MAX;
        if (!isset($b['#metadata'])) return PHP_INT_MIN;
        return $a['#metadata']['weight'] - $b['#metadata']['weight'];
      });
      foreach ($structure as $name => $data) {
        if ($name === '#metadata') continue;

        if (isset($data['#metadata']['url'])) {
          $child = Link::fromTextAndUrl($data['#metadata']['title'], Url::fromUri("internal:{$data['#metadata']['url']}"))->toRenderable();
        }
        else {
          $child = ['#markup' => $data['#metadata']['title']];
        }
        if (count($data) > 1) renderItemList($data, $child);
        $items['children'][] = $child;
      }
    }
    renderItemList($structure, $items);
    return [
      '#theme' => 'sitemap_item',
      '#content' => [
        '#theme' => 'item_list',
        '#items' => $items['children'],
      ],
      '#sitemap' => $this,
    ];
  }

}
