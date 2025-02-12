<?php

namespace Drupal\workbc_ssot\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Utility\Timer;
use GuzzleHttp\Client;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;

/**
 * SSOT data fetcher.
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
   * Upload the given LMMU sheet to the correct location in the workbc-ssot repo.
   * Use the GitHub API for repository contents https://docs.github.com/en/rest/repos/contents?apiVersion=2022-11-28
   */
  public function processItem($data) {
    $file = File::load($data['file_id']);
    $sheet = $file->getFilename();
    $repo = \Drupal::config('workbc')->get('ssot_repo');
    $filepath = \Drupal::service('file_system')->realpath($file->getFileUri());
    \Drupal::logger('workbc_ssot')->notice('Uploading SSoT LMMU sheet @sheet for @month/@year.', [
      '@sheet' => $sheet,
      '@month' => $data['month'],
      '@year' => $data['year'],
    ]);
    Timer::start('ssot_uploader');

    // First get the sha of the existing file if any.
    try {
      $existing = $this->github("https://api.github.com/repos/{$repo['name']}/contents/{$repo['path']}/{$sheet}?ref=" . $repo['branches'][getenv('PROJECT_ENVIRONMENT')], 'GET', $repo['token']);
      $sha = json_decode($existing->getBody())->sha;
    }
    catch (\Exception $e) {
      $sha = null;
    }

    // Create / update the file.
    $this->github("https://api.github.com/repos/{$repo['name']}/contents/{$repo['path']}/{$sheet}", 'PUT', $repo['token'], [
      'branch' => $repo['branches'][getenv('PROJECT_ENVIRONMENT')],
      'sha' => $sha,
      'content' => base64_encode(file_get_contents($filepath)),
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

    // Trigger the GitHub workflow.
    $this->github("https://api.github.com/repos/{$repo['name']}/dispatches", 'POST', $repo['token'], [
      'event_type' => 'monthly_labour_market_update',
      'client_payload' => [
        'branch' => $repo['branches'][getenv('PROJECT_ENVIRONMENT')],
        'filename' => $sheet,
        'year' => $data['year'],
        'month' => $data['month'],
        'date' => $data['date'],
        'notes' => $data['notes'],
      ]
    ]);

    Timer::stop('ssot_uploader');
    \Drupal::logger('workbc_ssot')->notice('Done uploading SSoT LMMU sheet @sheet in @time.', [
      '@sheet' => $sheet,
      '@time' => Timer::read('ssot_uploader') . 'ms'
    ]);
  }

  private function github($endpoint, $method, $token, $body = null) {
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
