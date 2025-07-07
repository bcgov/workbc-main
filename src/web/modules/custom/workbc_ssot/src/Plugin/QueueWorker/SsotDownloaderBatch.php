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
        \Drupal::logger('workbc')->warning('Could not find the following NOC in dataset @dataset: @noc. Ignoring.', [
          '@noc' => $career->get('field_noc')->value,
          '@dataset' => $dataset['endpoint'],
        ]);
        continue;
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

  private function update_wages($endpoint, $entries, &$career) {
    $career->set('field_annual_salary', reset($entries)['calculated_median_annual_salary']);
  }

  private function update_career_provincial($endpoint, $entries, &$career) {
    $openings = $career->get('field_region_openings')->getValue() ?? array_fill(0, 8, NULL_VALUE);
    $openings[REGION_BRITISH_COLUMBIA_ID] = reset($entries)['expected_job_openings_10y'] ?? NULL_VALUE;
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
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('epbc_categories');
    $categories = [];
    foreach ($entries as $entry) {
      $parent = array_find($terms, function ($v) use ($entry) {
        return $v->name === $entry['category'];
      });
      $term = array_find($terms, function ($v) use ($entry, $parent) {
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
    $career->set('field_job_titles_illustrative', array_column(array_filter($entries, function($title) {
      return !empty($title['illustrative']);
    }), 'commonjobtitle'));
  }

  private function update_high_opportunity_occupations($endpoint, $entries, &$career) {
    $openings = array_fill(0, 8, 0);
    $regions = ssotRegionIds();
    foreach ($entries as $entry) {
      $openings[$regions[$entry['region']]] = 1;
    }
    $career->set('field_region_hoo', $openings);
  }

  private function update_skills($endpoint, $entries, &$career) {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('skills');
    $skills = [];
    foreach ($entries as $entry) {
      $term = array_find($terms, function ($v) use ($entry) {
        return strcasecmp($v->name, $entry['skills_competencies']) === 0 && !empty($entry['importance']);
      });
      if ($term) {
        $skills[] = ['target_id' => $term->tid];
      }
    }
    $career->set('field_skills_2', $skills);
  }
}
