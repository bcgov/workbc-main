<?php

namespace Drupal\workbc_career_trek\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides custom Twig functions for Career Trek.
 */
class CareerTrekTwigExtension extends AbstractExtension {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new CareerTrekTwigExtension object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('career_trek_config', [$this, 'getCareerTrekConfig']),
      new TwigFunction('render_career_trek_job_posting', [$this, 'getCareerTrekJobPosting']),
    ];
  }

  public function getCareerTrekJobPosting($node_id, $block_id) {
    $entity_type_manager = \Drupal::entityTypeManager();
    $node_storage = $entity_type_manager->getStorage('node');
    $node = $node_storage->load($node_id);
    if ($node) {
      $blocks = \Drupal::service('entity_type.manager')
        ->getStorage('block')
        ->load($block_id);

      if ($blocks) {
        $renderer = \Drupal::service('renderer');
        $blocks->getPlugin()->setConfigurationValue('node_id', $node_id);
        $build = $blocks->getPlugin()->build();
        return $renderer->render($build);
      }
    }
    return '';
  }

  /**
   * Returns the configuration value for the given key.
   *
   * @param string $key
   *   The configuration key.
   *
   * @return mixed
   *   The configuration value.
   */
  public function getCareerTrekConfig($key) {
    $config = $this->configFactory->get('workbc_career_trek.settings');
    // If the key is 'logo', retrieve the file URL (now stored as an absolute path).
    if ($key === 'logo') {
      $path = $config->get($key);
      if (!empty($path)) {
        // If the path is relative to the Drupal root, prepend base_path().
        // If it's already absolute (starts with /), just return as is.
        global $base_url;
        // Remove leading slash if present to avoid double slashes.
        $url = $base_url . (strpos($path, '/') === 0 ? $path : '/' . $path);
        return $url;
      }
      return NULL;
    }
    // For 'toggle_icon_grid', 'toggle_icon_list', 'responsive_toggle_icon', return the SVG content from the path.
    elseif ($key === 'toggle_icon_grid' || $key === 'toggle_icon_list' || $key === 'responsive_toggle_icon') {
      $path = $config->get($key);
      if (!empty($path)) {
        // The path is absolute from the Drupal root, e.g. "/themes/custom/..."
        // Build the full filesystem path.
        $drupal_root = \Drupal::root();
        $file_path = $drupal_root . $path;
        if (file_exists($file_path)) {
          $file_content = file_get_contents($file_path);
          if ($file_content) {
            return new \Twig\Markup($file_content, 'UTF-8');
          }
        }
      }
      return NULL;
    }

    return $config->get($key);
  }

}
