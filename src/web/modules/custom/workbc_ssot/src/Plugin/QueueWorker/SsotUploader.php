<?php

namespace Drupal\workbc_ssot\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Utility\Timer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;

/**
 * SSOT data updater.
 *
 * @QueueWorker(
 *   id = "ssot_uploader",
 *   title = @Translation("SSoT Uploader"),
 *   cron = {"time" = 60}
 * )
 */
class SsotUploader extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
   * Upload the workbook and dispatch its metadata for backend ingestion.
   */
  public function processItem($data) {
   \Drupal::logger('workbc')->notice('queue - processing item.');
    $file = File::load($data['file_id']);
    $sheet = $file->getFilename();
    $repo = \Drupal::config('workbc')->get('ssot_repo');
    $filepath = \Drupal::service('file_system')->realpath($file->getFileUri());
    \Drupal::logger('workbc')->notice('Uploading SSoT sheet @sheet for @month/@year.', [
      '@sheet' => $sheet,
      '@month' => $data['month'],
      '@year' => $data['year'],
    ]);
    Timer::start('ssot_uploader');

    $content = file_get_contents($filepath);
    if ($content === FALSE) {
      throw new \RuntimeException('Unable to read the uploaded workbook.');
    }
    $endpoint = "https://api.github.com/repos/{$repo['name']}/contents/{$repo['path']}/" . rawurlencode($sheet);
    $sha = null;
    try {
      $existing = $this->github($endpoint . '?ref=' . rawurlencode($repo['branch']), 'GET', $repo['token']);
      $sha = json_decode($existing->getBody(), TRUE, 512, JSON_THROW_ON_ERROR)['sha'];
    }
    catch (ClientException $error) {
      if ($error->getResponse()->getStatusCode() !== 404) {
        throw $error;
      }
    }
    $uploaded = $this->github($endpoint, 'PUT', $repo['token'], [
      'branch' => $repo['branch'],
      'sha' => $sha,
      'content' => base64_encode($content),
      'committer' => [
        'name' => $repo['committer'],
        'email' => \Drupal::config('system.site')->get('mail'),
      ],
      'author' => [
        'name' => User::load($data['uid'])->getDisplayName(),
        'email' => \Drupal::config('system.site')->get('mail'),
      ],
      'message' => $data['notes'],
    ]);
    $workbook_sha = json_decode($uploaded->getBody(), TRUE, 512, JSON_THROW_ON_ERROR)['content']['sha'] ?? null;
    if (!is_string($workbook_sha) || !preg_match('/^[a-f0-9]{40}$/i', $workbook_sha)) {
      throw new \UnexpectedValueException('GitHub did not return the uploaded workbook SHA.');
    }

    // Trigger the GitHub workflow.
    $this->github("https://api.github.com/repos/{$repo['name']}/dispatches", 'POST', $repo['token'], [
      'event_type' => 'ssot_update',
      'client_payload' => [
        'branch' => $repo['branch'],
        'filename' => $sheet,
        'year' => $data['year'],
        'month' => $data['month'],
        'date' => $data['date'],
        'notes' => $data['notes'],
        'workbook_sha' => $workbook_sha,
        'metadata' => [
          'label' => $data['label'] ?? null,
          'author' => $data['author'] ?? null,
          'datasets' => $data['datasets'],
        ],
      ]
    ]);

    Timer::stop('ssot_uploader');
    \Drupal::logger('workbc')->notice('Done uploading SSoT sheet @sheet in @time.', [
      '@sheet' => $sheet,
      '@time' => Timer::read('ssot_uploader') . 'ms'
    ]);
  }

  protected function github($endpoint, $method, $token, $body = null) {
    $client = new Client();
    $options = [
      'headers' => [
        'Accept' => 'application/vnd.github+json',
        'Authorization' => "Bearer {$token}",
        'X-GitHub-Api-Version' => '2022-11-28',
      ]
    ];
    if (!empty($body)) {
      $options['json'] = $body;
    }
    return $client->request($method, $endpoint, $options);
  }
}
