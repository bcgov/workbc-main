<?php

namespace Drupal\workbc_ssot\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * SSOT data fetcher.
 *
 * @QueueWorker(
 *   id = "ssot_downloader_batch",
 *   title = @Translation("SSoT Downloader (Batch)"),
 *   cron = {"time" = 60}
 * )
 */
class SsotDownloaderBatch extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use SsotUpdater;

  /**
  * Main constructor.
  *
  * @param array $configuration
  *   Configuration array.
  * @param mixed $plugin_id
  *   The plugin id.
  * @param mixed $plugin_definition
  *   The plugin definition.
  */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * Download the requested SSOT dataset.
   * Update the corresponding fields in each career_profile accordingly.
   * Update the local dataset update date.
   */
  public function processItem($data) {
    $career = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->load($data['nid']);

    // Update the node with each dataset.
    foreach ($data['datasets'] as $dataset) {
      $method = 'update_' . $dataset['endpoint'];
      if (!method_exists($this, $method)) {
        \Drupal::logger('workbc')->error('Could not find the method @method for dataset @dataset. Skipping.', [
          '@method' => $method,
          '@dataset' => $dataset['endpoint'],
        ]);
        continue;
      }
      if (empty($dataset['data'])) {
        if (empty($dataset['call_if_missing'])) {
          \Drupal::logger('workbc')->warning('Could not find the following NOC in dataset @dataset: @noc. Ignoring.', [
            '@noc' => $career->get('field_noc')->value,
            '@dataset' => $dataset['endpoint'],
          ]);
          continue;
        }
      }
      $this->$method($dataset['endpoint'], $dataset['data'], $career);
    }

    // Save the node.
    $career->setNewRevision(true);
    $career->setRevisionLogMessage('Updating SSOT datasets: ' . join(', ', array_column($data['datasets'], 'endpoint')));
    $career->setRevisionCreationTime(time());
    $career->setRevisionUserId(1);
    $career->save();
  }
}
