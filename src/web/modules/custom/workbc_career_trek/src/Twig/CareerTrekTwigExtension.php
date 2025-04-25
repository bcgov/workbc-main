<?php

namespace Drupal\workbc_career_trek\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileUrlGeneratorInterface;

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
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * Constructs a new CareerTrekTwigExtension object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, FileUrlGeneratorInterface $fileUrlGenerator) {
    $this->configFactory = $configFactory;
    $this->fileUrlGenerator = $fileUrlGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('career_trek_config', [$this, 'getCareerTrekConfig']),
    ];
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
    // If the key is 'logo', retrieve the file URL.
    if ($key === 'logo') {
      $fid = $config->get($key);
      if (!empty($fid) && is_array($fid)) {
        $fid = reset($fid); // Get the first file ID if it's an array.
      }
      if ($fid) {
        $file = File::load($fid);
        if ($file) {
          return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
        }
      }
      return NULL; // Return NULL if no valid file exists.
    }
    // For 'toggle_icon_grid' and 'toggle_icon_list', return the SVG content.
    elseif ($key === 'toggle_icon_grid' || $key === 'toggle_icon_list' || $key === 'responsive_toggle_icon') {
      $fid = $config->get($key);
      if (!empty($fid) && is_array($fid)) {
        $fid = reset($fid); // Get the first file ID if it's an array.
      }
      if ($fid) {
        $file = File::load($fid);
        if ($file) {
          $file_uri = $file->getFileUri();
          $file_content = file_get_contents($file_uri);
          if ($file_content) {
            return new \Twig\Markup($file_content, 'UTF-8'); // Return the SVG content as a Twig Markup object.
          }
        }
      }
      return NULL; // Return NULL if no valid file exists.
    }

    return $config->get($key);
  }

}
