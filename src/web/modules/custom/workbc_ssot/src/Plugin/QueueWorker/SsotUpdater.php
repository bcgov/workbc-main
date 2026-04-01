<?php

namespace Drupal\workbc_ssot\Plugin\QueueWorker;

use Drupal\media\Entity\Media;

trait SsotUpdater {
  private $epbc_categories;
  private $skills;
  private $occupational_interests;

  public function update_wages($endpoint, $entries, &$career) {
    $career->set('field_annual_salary', reset($entries)['calculated_median_annual_salary']);
    $career->set('field_hourly_salary', reset($entries)['esdc_wage_rate_median']);
  }

  public function update_career_provincial($endpoint, $entries, &$career) {
    $openings = $career->get('field_region_openings')->getValue() ?? array_fill(0, 8, NULL_VALUE);
    $openings[REGION_BRITISH_COLUMBIA_ID] = reset($entries)['expected_job_openings_10y'] ?? NULL_VALUE;
    $career->set('field_region_openings', $openings);
  }

  public function update_career_regional($endpoint, $entries, &$career) {
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

  public function update_career_trek($endpoint, $entries, &$career) {
    $videos = [];
    foreach ($entries as $entry) {
      // Query each incoming video in Media Library.
      preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $entry['youtube_link'], $match);
      $youtube_id = $match[1];
      $mid = array_values(\Drupal::entityQuery('media')
        ->condition('bundle', 'remote_video')
        ->condition('field_media_oembed_video', $youtube_id, 'CONTAINS')
        ->accessCheck(false)
        ->currentRevision()
        ->execute());
      if (empty($mid)) {
        // Create missing video in Media Library.
        $media = Media::create([
          'bundle'=> 'remote_video',
          'uid' => 1,
          'field_media_oembed_video' => $entry['youtube_link']
        ]);
      }
      else {
        // Update the existing video with incoming data.
        $media = Media::load(reset($mid));
      }
      $media
        ->setPublished()
        ->setName($entry['episode_title'])
        ->set('field_description', $entry['description'])
        ->set('field_episode', $entry['episode_num'])
        ->set('field_location', $entry['location'])
        ->set('field_region', $entry['region'])
        ->set('field_noc', $entry['noc_2021'])
        ->save();

      $videos[] = ['target_id' => $media->id()];
    }

    // Reset the Career Profiles video references.
    $career->set('field_career_videos', $videos);
  }

  public function update_fyp_categories_interests($endpoint, $entries, &$career) {
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

  public function update_education($endpoint, $entries, &$career) {
    $career->set('field_teer', reset($entries)['teer']);
  }

  public function update_titles($endpoint, $entries, &$career) {
    $career->set('field_job_titles', array_column($entries, 'commonjobtitle'));
    $career->set('field_job_titles_illustrative', array_column(array_filter($entries, function($title) {
      return !empty($title['illustrative']);
    }), 'commonjobtitle'));
  }

  public function update_high_opportunity_occupations($endpoint, $entries, &$career) {
    $openings = array_fill(0, 8, 0);
    $regions = ssotRegionIds();
    if (!empty($entries)) foreach ($entries as $entry) {
      $openings[$regions[$entry['region']]] = 1;
    }
    $career->set('field_region_hoo', $openings);
  }

  public function update_skills($endpoint, $entries, &$career) {
    // Cache the skills vocabulary.
    if (!isset($this->skills)) {
      $this->skills = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('skills');
    }

    // Sort and limit the incoming skills by importance and proficiency.
    $filteredSkills = array_filter($entries, function($entry) {
      return intval($entry['importance']) > 0;
    });
    array_multisort(
      array_column($filteredSkills, 'importance'), SORT_DESC,
      array_column($filteredSkills, 'proficiency'), SORT_DESC,
      array_column($filteredSkills, 'skills_competencies'), SORT_ASC,
      $filteredSkills
    );
    $filteredSkills = array_slice($filteredSkills, 0, 10);

    // Match the filtered skills to the vocabulary.
    $skills = [];
    foreach ($filteredSkills as $entry) {
      $term = array_find($this->skills, function ($v) use ($entry) {
        return strcasecmp($v->name, $entry['skills_competencies']) === 0;
      });
      if ($term) {
        $skills[] = ['target_id' => $term->tid];
      }
    }
    $career->set('field_skills_2', $skills);
  }

  public function update_occupational_interests($endpoint, $entries, &$career) {
    if (!isset($this->occupational_interests)) {
      $this->occupational_interests = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('occupational_interests');
    }
    $interests = [];
    foreach ($entries as $entry) {
      $term = array_find($this->occupational_interests, function ($v) use ($entry) {
        return strcasecmp($v->name, $entry['occupational_interest']) === 0;
      });
      if ($term) {
        $interests[$entry['options']] = ['target_id' => $term->tid];
      }
    }
    $order = ['Primary', 'Secondary', 'Tertiary'];
    uksort($interests, function($a, $b) use($order) {
      return array_search($a, $order) - array_search($b, $order);
    });
    $career->set('field_occupational_interests', array_values($interests));
  }
}
