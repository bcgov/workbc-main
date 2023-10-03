<?php

namespace Drupal\ckeditor5_media_dl\Plugin\Filter;

use Drupal\ckeditor5\Plugin\CKEditor5PluginManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Psr\Container\ContainerInterface;

/**
 * Provides a filter to convert <media-download-link> tag into a <a> with link to download file by media id.
 *
 * @Filter(
 *   id = "filter_media_download_link",
 *   title = @Translation("Convert media download links (CK5)"),
 *   description = @Translation("Convert custom <code>&lt;media-download-link&gt;</code> tag into <code>&lt;a&gt;</code> tag with link into <code>/media/[media-id]/download?inline</code> format."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   weight = 90,
 * )
 */
class MediaDownloadLinkFilter extends FilterBase implements ContainerFactoryPluginInterface {

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
    $result = new FilterProcessResult($text);


    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    foreach ($xpath->query('//a[@data-entity-loader="dlcke" and normalize-space(@data-uuid)!=""]') as $node) {
      /** @var \DOMElement $node */
      $uuid = $node->getAttribute('data-uuid');

      $media = \Drupal::service('entity.repository')->loadEntityByUuid('media', $uuid);
      assert($media === NULL || $media instanceof MediaInterface);
      if (!$media) {
        $this->loggerFactory->get('media')->error('During replacement of dlcke media id: the media item with UUID "@uuid" does not exist.', ['@uuid' => $uuid]);
        return $result;
      } else {
        $text = str_replace('[dlcke:'.$uuid.']', $media->id(), $text);
      }
    }

    return new FilterProcessResult($text);
  }


}
