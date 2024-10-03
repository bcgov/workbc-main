<?php

namespace Drupal\media_entity_dlcke\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginConfigurableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Entity\Editor;
use Drupal\ckeditor\CKEditorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Defines the "mediaentitydlcke" plugin.
 *
 * @CKEditorPlugin(
 *   id = "mediaentitydlcke",
 *   label = @Translation("Media download link"),
 *   module = "media_entity_dlcke"
 * )
 */
class MediaEntityDLCKE extends CKEditorPluginBase implements CKEditorPluginConfigurableInterface, ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * MediaEntityDLCKE constructor.
   *
   * @param array $configuration
   *   The configuration of the plugin.
   * @param string $plugin_id
   *   The id of the plugin.
   * @param mixed $plugin_definition
   *   The definition of the plugin.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service from the container.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    $path = Drupal\Core\Extension\ExtensionPathResolver::getPath('module', 'media_entity_dlcke');
    return $path . '/js/plugins/mediaentitydlcke/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    $config = [];
    $settings = $editor->getSettings();
    if (!isset($settings['plugins']['mediaentitydlcke']['entity_browser'])) {
      return $config;
    }
    $config['entity_browser'] = $settings['plugins']['mediaentitydlcke']['entity_browser'];
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    $path = Drupal\Core\Extension\ExtensionPathResolver::getPath('module', 'media_entity_dlcke');
    $path .= '/js/plugins/mediaentitydlcke';
    return [
      'MediaEntityDLCKE' => [
        'label' => $this->t('Media Library download link'),
        'image' => $path . '/icons/mediaentitydlcke.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {
    // Defaults.
    $config = ['entity_browser' => ''];
    $settings = $editor->getSettings();
    if (isset($settings['plugins']['mediaentitydlcke'])) {
      $config = $settings['plugins']['mediaentitydlcke'];
    }

    /** @var \Drupal\entity_browser\EntityBrowserInterface[] $browsers */
    if ($this->entityTypeManager->hasDefinition('entity_browser') && ($browsers = $this->entityTypeManager->getStorage('entity_browser')->loadMultiple())) {
      $ids = array_keys($browsers);
      $labels = array_map(
        function ($item) {
          /** @var \Drupal\entity_browser\EntityBrowserInterface $item */
          return $item->label();
        },
        $browsers
      );
      $options = ['_none' => $this->t('None (autocomplete)')] + array_combine($ids, $labels);
      $form['entity_browser'] = [
        '#type' => 'select',
        '#title' => $this->t('Entity browser'),
        '#description' => $this->t('Entity browser to be used to select entities to be embedded.'),
        '#options' => $options,
        '#default_value' => $config['entity_browser'],
      ];
    }
    // dpm($this->entityTypeManager->hasDefinition('entity_browser'));
    // dpm($this->entityTypeManager->getStorage('entity_browser'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [
      'core/jquery',
      'core/drupal.ajax',
    ];
  }

}
