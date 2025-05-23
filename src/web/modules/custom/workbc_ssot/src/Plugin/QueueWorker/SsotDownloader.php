<?php

namespace Drupal\workbc_ssot\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Utility\Timer;

define("NULL_VALUE", -999999);

/**
 * SSOT data fetcher.
 *
 * @QueueWorker(
 *   id = "ssot_downloader",
 *   title = @Translation("SSOT Downloader"),
 *   cron = {"time" = 60}
 * )
 */
class SsotDownloader extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  private $epbc_categories;

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
    \Drupal::logger('workbc')->notice('Updating SSOT datasets: @datasets', [
      '@datasets' => join(', ', array_map(function($dataset) {
        return $dataset->endpoint;
      }, $data['datasets']))
    ]);
    Timer::start('ssot_downloader');

    // Load all career profiles.
    $storage = \Drupal::service('entity_type.manager')->getStorage('node');
    $careers = $storage->loadByProperties([
      'type' => 'career_profile',
    ]);

    // Load updated datasets.
    $updated_datasets = [];
    foreach ($data['datasets'] as $dataset) {
      $metadata = SSOT_DATASETS[$dataset->endpoint];

      // Formulate SSOT query given dataset information.
      $endpoint = (array_key_exists('endpoint', $metadata) ? $metadata['endpoint'] : $dataset->endpoint) . '?' . http_build_query(array_merge(
        ['select' => $metadata['fields']],
        array_key_exists('filters', $metadata) ? $metadata['filters'] : [],
        array_key_exists('order', $metadata) ? ['order' => $metadata['order']] : [],
      ));
      $result = ssot($endpoint);
      if (!$result) {
        \Drupal::logger('workbc')->error('Error fetching SSOT dataset @dataset at @endpoint. Skipping.', [
          '@dataset' => $dataset->endpoint,
          '@endpoint' => $endpoint,
        ]);
        continue;
      };

      // Index the dataset by NOC.
      $entries = array_reduce(json_decode($result->getBody(), true), function($entries, $entry) use($metadata) {
        $noc = $entry[$metadata['noc_key']];
        if (array_key_exists($noc, $entries)) {
          $entries[$noc][] = $entry;
        }
        else {
          $entries[$noc] = [$entry];
        }
        return $entries;
      }, []);

      // Update each career with the dataset-specific update function.
      $method = 'update_' . $dataset->endpoint;
      if (!method_exists($this, $method)) {
        \Drupal::logger('workbc')->error('Could not find the method @method for dataset @dataset. Skipping.', [
          '@method' => $method,
          '@dataset' => $dataset->endpoint,
        ]);
        continue;
      }

      $missing_nocs = [];
      foreach ($careers as &$career) {
        $noc = $career->get('field_noc')->value;
        if (empty($noc)) {
          continue;
        }
        if (!array_key_exists($noc, $entries)) {
          $missing_nocs[] = $noc;
          continue;
        }
        $entry = $entries[$noc];
        $this->$method($dataset->endpoint, $entry, $career);
      }
      if (!empty($missing_nocs)) {
        \Drupal::logger('workbc')->warning('Could not find the following NOCs in dataset @dataset: @nocs', [
          '@nocs' => join(', ', $missing_nocs),
          '@dataset' => $dataset->endpoint,
        ]);
      }

      // Indicate we have updated this dataset.
      $updated_datasets[$dataset->endpoint] = $dataset->date;
    }

    // Save the careers.
    foreach ($careers as &$career) {
      $career->setNewRevision(true);
      $career->setRevisionLogMessage('Updating SSOT datasets: ' . join(', ', array_keys($updated_datasets)));
      $career->setRevisionCreationTime(time());
      $career->setRevisionUserId(1);
      $career->save();
    }

    // Update local date for updated datasets.
    $local_dates = array_merge(array_combine(
      array_keys(SSOT_DATASETS),
      array_fill(0, count(SSOT_DATASETS), null)
    ), \Drupal::state()->get('workbc.ssot_dates', []));
    foreach ($updated_datasets as $endpoint => $ssot_date) {
      $local_dates[$endpoint] = $ssot_date;
    }
    \Drupal::state()->set('workbc.ssot_dates', $local_dates);

    Timer::stop('ssot_downloader');
    \Drupal::logger('workbc')->notice('Updated following SSOT datasets in @time: @datasets', [
      '@datasets' => join(', ', array_keys($updated_datasets)),
      '@time' => Timer::read('ssot_downloader') . 'ms'
    ]);
  }

  private function update_wages($endpoint, $entries, &$career) {
    $career->set('field_annual_salary', reset($entries)['calculated_median_annual_salary']);
  }

  private function update_career_provincial($endpoint, $entries, &$career) {
    $openings = $career->get('field_region_openings')->getValue() ?? array_fill(0, 8, 0);
    $openings[REGION_BRITISH_COLUMBIA_ID] = reset($entries)['expected_job_openings_10y'] ?? 0;
    $career->set('field_region_openings', $openings);
  }

  private function update_career_regional($endpoint, $entries, &$career) {
    $openings = $career->get('field_region_openings')->getValue() ?? array_fill(0, 8, NULL_VALUE);
    $entry = reset($entries);
    $openings[REGION_CARIBOO_ID] = $entry['cariboo_expected_number_of_job_openings_10y'] ?? NULL_VALUE;
    $openings[REGION_KOOTENAY_ID] = $entry['kootenay_expected_number_of_job_openings_10y'] ?? NULL_VALUE;
    $openings[REGION_MAINLAND_SOUTHWEST_ID] = $entry['mainland_southwest_expected_number_of_job_openings_10y'] ?? NULL_VALUE;
    $openings[REGION_NORTH_COAST_NECHAKO_ID] = $entry['north_coast_nechako_expected_number_of_job_openings_10y'] ?? NULL_VALUE;
    $openings[REGION_NORTHEAST_ID] = $entry['northeast_expected_number_of_job_openings_10y'] ?? NULL_VALUE;
    $openings[REGION_THOMPSON_OKANAGAN_ID] = $entry['thompson_okanagan_expected_number_of_job_openings_10y'] ?? NULL_VALUE;
    $openings[REGION_VANCOUVER_ISLAND_COAST_ID] = $entry['vancouver_island_coast_expected_number_of_job_openings_10y'] ?? NULL_VALUE;
    $career->set('field_region_openings', $openings);
  }

  private function update_fyp_categories_interests($endpoint, $entries, &$career) {
    if (!isset($this->epbc_categories)) {
      $this->epbc_categories = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('epbc_categories');
    }
    $categories = [];
    foreach ($entries as $entry) {
      $parent = array_find($this->epbc_categories, function ($v) use ($entry) {
        return $v->name === $entry['category'];
      });
      $term = array_find($this->epbc_categories, function ($v) use ($entry, $parent) {
        return $v->name === $entry['interest'] && $v->parents[0] === $parent->tid;
      });
      $categories[] = ['target_id' => $term->tid];
    }
    $career->set('field_epbc_categories', $categories);
  }

  private function update_education($endpoint, $entries, &$career) {
    $career->set('field_teer', reset($entries)['teer']);
  }

  private function update_titles($endpoint, $entries, &$career) {
    $career->set('field_job_titles', array_column($entries, 'commonjobtitle'));
  }
}
