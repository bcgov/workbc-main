<?php

namespace Drupal\workbc_cdq_ssot\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Utility\Timer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;

/**
 * SSOT data fetcher.
 *
 * @QueueWorker(
 *   id = "ssot_uploader",
 *   title = @Translation("SSOT Uploader"),
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
   * Upload the given LMMU sheet to the correct location in the workbc-ssot repo.
   */
  public function processItem($data) {
    $file = File::load($data['sheet']);
    \Drupal::logger('workbc')->notice('Uploading SSOT LMMU sheet @sheet for @month/@year.', [
      '@sheet' => $file->getFilename(),
      '@month' => $data['month'],
      '@year' => $data['year'],
    ]);
    Timer::start('ssot_uploader');

    $repo = \Drupal::config('workbc')->get('ssot_repo');
    $response = $this->github("https://api.github.com/repos/{$repo['name']}/contents/migration/data/{$data['sheet']}", 'PUT', [
      'branch' => $repo['branches'][getenv('PROJECT_ENVIRONMENT')],
//      'sha' => hash_file('sha256', \Drupal::service('file_system')->realpath($file->getFileUri())),
      'content' => base64_encode(file_get_contents(\Drupal::service('file_system')->realpath($file->getFileUri()))),
      'committer' => [
        'name' => $repo['committer'],
        'email' => \Drupal::config('system.site')->get('mail'),
      ],
      'author' => [
        'name' => User::load($data['uid'])->getDisplayName(),
        'email' => \Drupal::config('system.site')->get('mail'),
      ],
      'message' => $data['notes'],
    ], $repo['token']);

    Timer::stop('ssot_uploader');
    \Drupal::logger('workbc')->notice('Done uploading SSOT LMMU sheet @sheet in @time.', [
      '@sheet' => $data['sheet'],
      '@time' => Timer::read('ssot_uploader') . 'ms'
    ]);
  }

  private function github($endpoint, $method, $body, $token) {
    $client = new Client();
    try {
      $options = [
        'body' => $body,
        'headers' => [
          'Accept' => 'application/vnd.github+json',
          'Authorization' => "Bearer {$token}",
          'X-GitHub-Api-Version' => '2022-11-28',
        ]
      ];
      return $client->request($method, $endpoint, $options);
    }
    catch (RequestException $e) {
      \Drupal::logger('workbc_ssot')->error($e->getMessage());
      return null;
    }
  }
}
