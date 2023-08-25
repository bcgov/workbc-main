<?php

namespace Drupal\ckeditor_media_resize\Plugin\Filter;

use Drupal\ckeditor5\Plugin\CKEditor5PluginManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Psr\Container\ContainerInterface;

/**
 * Provides a filter to apply resizing of media images.
 *
 * @Filter(
 *   id = "filter_resize_media",
 *   title = @Translation("Resize media images"),
 *   description = @Translation("Uses a <code>data-media-width</code> attribute on <code>&lt;drupal-media&gt;</code> tags to apply resizing of media images. This filter needs to run before the <strong>Embed media</strong> filter and requires the <strong>Limit allowed HTML tags and correct faulty HTML</strong> to be active."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   weight = 90,
 * )
 */
class FilterResizeMedia extends FilterBase implements ContainerFactoryPluginInterface {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected CKEditor5PluginManagerInterface $ckeditor5PluginManager;
  protected CurrentRouteMatch $routeMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->ckeditor5PluginManager = $container->get('plugin.manager.ckeditor5.plugin');
    $instance->routeMatch = $container->get('current_route_match');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $ckeditor5_plugin = $this->ckeditor5PluginManager
      ->createInstance('ckeditor_media_resize_mediaResize');
    $ckeditor5_plugin_config = $ckeditor5_plugin->getConfiguration();
    $result = new FilterProcessResult($text);
    $data_attribute = 'data-media-width';

    // Apply image styles only if the corresponding setting in the text format
    // configuration is enabled and if this filter processing is NOT happening
    // during the loading of the processed text inside the ckeditor.
    $apply_image_styles = !empty($ckeditor5_plugin_config['apply_image_styles'])
      && $this->routeMatch->getCurrentRouteMatch()->getRouteName() != 'media.filter.preview';

    if (stristr($text, $data_attribute) !== FALSE) {
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);
      /** @var \DOMNode $node */
      foreach ($xpath->query('//*[@' . $data_attribute . ']') as $node) {
        [$width, $attribute_value] = $this->getStyleAttributeFromNode($node, $data_attribute);
        $node->setAttribute('style', $attribute_value);
        if ($apply_image_styles) {
          $node->setAttribute(
            'data-view-mode',
            $this->getViewModeByWidth($width, $ckeditor5_plugin_config)
          );
        }
      }

      foreach ($xpath->query('//figure/drupal-media[@' . $data_attribute . ']') as $node) {
        [$width, $attribute_value] = $this->getStyleAttributeFromNode($node, $data_attribute);
        // If the figure was used on this media, apply the width to the figure.
        $node->parentNode->setAttribute('style', $attribute_value);
        if ($apply_image_styles) {
          $node->setAttribute(
            'data-view-mode',
            $this->getViewModeByWidth($width, $ckeditor5_plugin_config)
          );
        }
      }

      $result->setProcessedText(Html::serialize($dom));
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      return $this->t('
        <p>Applies the resizing on embedded media images inside ckeditor5 generated content when rendering the ckeditor content in Drupal.</p>
        <p>This works via a the <code>data-media-width</code> attribute on <code><drupal-media></code> tags, for example: <code><drupal-media data-media-width="50%"</code>.</p>
      ');
    }
    else {
      return $this->t('You can resize media images by adding the <code>data-media-width</code> attribute on <code><drupal-media></code> tags, for example: <code><drupal-media data-media-width="50%"</code>.');
    }
  }

  /**
   * Gets appended $width style attribute and for given node and attribute.
   */
  public function getStyleAttributeFromNode(\DOMNode $node, string $data_attribute): array {
    $width = $node->getAttribute($data_attribute);
    $node->removeAttribute($data_attribute);
    $attribute_value = $node->getAttribute('style');

    // Replace existing width style with new one.
    $styles = explode(';', $attribute_value);
    $to_replace = '';
    foreach ($styles as $style) {
      if (\mb_strpos($style, 'width') === 0) {
        $to_replace = $style;
        break;
      }
    }
    if ($to_replace) {
      $attribute_value = str_replace($to_replace, 'width:' . $width, $attribute_value);
    }
    else {
      $attribute_value = 'width:' . $width . ';';
    }

    return [(int) $width, $attribute_value];
  }

  /**
   * Determines which view mode the resized media image should be rendered with.
   */
  protected function getViewModeByWidth(int $width, array $ckeditor5_plugin_config): string {
    $image_styles = $this->entityTypeManager->getStorage('image_style')
      ->loadMultiple($ckeditor5_plugin_config['image_styles']);
    $image_styles_widths_map = $this->getImageStyleWidths($image_styles);
    foreach ($image_styles_widths_map as $image_style_name => $image_style_width) {
      if ($image_style_width >= $width) {
        return $image_style_name;
      }
    }
    return $image_style_name;
  }

  /**
   * Extracts width options from given image styles and their effects.
   */
  protected function getImageStyleWidths(array $image_styles): array {
    $widths = [];
    foreach ($image_styles as $name => $style) {
      $width = 0;
      foreach ($style->getEffects() as $effect) {
        $effect_config = $effect->getConfiguration();
        if (!empty($effect_config['data']['width'])) {
          $width = max($width, $effect_config['data']['width']);
        }
      }
      $widths[$name] = $width;
    }
    \asort($widths, \SORT_NUMERIC);
    return \array_filter($widths);
  }

}
