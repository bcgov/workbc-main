<?php

namespace Drupal\workbc_career_trek\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Set featured flag of a media entity.
 *
 * @Action(
 *   id = "media_set_featured",
 *   label = @Translation("Set featured video")
 * )
 */
class SetFeaturedAction extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The media entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaStorage;

  /**
   * Creates a new UpdateMetadataAction plugin instance.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The entity storage.
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(EntityStorageInterface $entity_storage, array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->mediaStorage = $entity_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager')->getStorage('media'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if ($entity) {
      /** @var \Drupal\media\MediaInterface $entity */
      $entity->set('field_recently_added', TRUE);
      $this->mediaStorage->save($entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\Core\Entity\EntityInterface $object */
    $result = $object->access('update', $account, TRUE);
    return $return_as_object ? $result : $result->isAllowed();
  }

}
