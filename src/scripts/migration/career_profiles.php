<?php

/**
 * Generate career profile nodes from SSoT entries and optional career_profiles.json import from GatherContent.
 * Source: /wages (WorkBC_2021_Wage_Data)
 *
 * Usage: drush scr /scripts/migration/career_profiles
 *
 * Revert: drush entity:delete node --bundle=career_profile
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Read GatherContent career profiles if present.
$career_profiles = NULL;
if (file_exists(__DIR__ . '/data/career_profiles.json')) {
  $data = json_decode(file_get_contents(__DIR__ . '/data/career_profiles.json'));
  foreach ($data as $i => $career_profile) {
    $noc = NULL;
    if (!preg_match('/\d+/', $career_profile->NOC, $noc)) {
      die("[WorkBC Migration] Could not find NOC in record $i of career_profiles.json. Aborting!" . PHP_EOL);
    }
    $career_profiles[$noc[0]] = $career_profile;
  }
}

$ssot = rtrim(\Drupal::config('workbc')->get('ssot_url'), '/');
$client = new Client();
try {
  $response = $client->get($ssot . '/wages');
  $result = json_decode($response->getBody(), TRUE);
  foreach ($result as $profile) {
    $fields = [
      'type' => 'career_profile',
      'title' => $profile['occupation_title'],
      'field_noc' => $profile['noc'],
      'uid' => 1,
    ];
    print("Creating {$fields['title']}\n");

    // Check GC import for this noc.
    if (array_key_exists($profile['noc'], $career_profiles)) {
      print("  Found a GatherContent record for this profile\n");

      $career_profile = $career_profiles[$profile['noc']];
      $fields = array_merge($fields, [
        'field_career_overview_intro' => convertRichText($career_profile->{'Career Overview Content'}),
        'field_duties' => convertRichText($career_profile->{'Duties Content'}),
        'field_additional_duties' => convertRichText($career_profile->{'Additional Duties List'}),
        'field_salary_introduction' => convertRichText($career_profile->{'Salary Content'}),
        'field_work_environment' => convertRichText($career_profile->{'Work Environment Content'}),
        'field_career_pathways' => convertRichText($career_profile->{'Career Pathways Content'}),
//        'field_related_careers' => $career_profile->{'Related Careers Content'},
        'field_occupational_interests_int' => convertRichText($career_profile->{'Occupational Interests Content'}),
        'field_job_titles' => convertMultiple($career_profile->{'Job Titles List'}),
        'field_career_videos' => convertVideos($career_profile->{'Career Video URLs'}),
        'field_career_videos_introduction' => convertRichText($career_profile->{'Career Videos Content'}),
        'field_education_training_skills' => convertRichText($career_profile->{'Education, Training and Skills Content'}),
        'field_education_programs' => convertRichText($career_profile->{'Education Programs in B.C. Content'}),
        'field_skills_introduction' => convertRichText($career_profile->{'Skills Content'}),
        'field_labour_market_introduction' => convertRichText($career_profile->{'Labour Market Statistics Content'}),
//        '???' => $career_profile->{'Labour Market Outlook Content'},
//        '???' => $career_profile->{'Employment Content'};
        'field_industry_highlights_intro' => convertRichText($career_profile->{'Industry Highlights Content'}),
        'field_insights_from_industry' => convertRichText($career_profile->{'Insights from Industry Content'}),
        'field_career_overview_intro' => convertRichText($career_profile->{'Career Overview Content'}),
//        'field_hero_image' => $career_profile->{'Banner Image'},
        'field_resources' => convertResources($career_profile->{'Resources'}),
      ]);
    }

    $node = Drupal::entityTypeManager()
      ->getStorage('node')
      ->create($fields);
    $node->save();
  }
}
catch (RequestException $e) {
  print($e->getMessage());
}

function convertRichText($field) {
  return ['format' => 'full_html', 'value' => $field];
}

function convertMultiple($multi_field) {
  return array_filter(array_map('trim', explode("\n", $multi_field)));
}

function convertVideos($urls) {
  $targets = [];
  foreach (convertMultiple($urls) as $url) {
    $fields = [
      'bundle' => 'remote_video',
      'uid' => 1,
      'field_media_oembed_video' => $url,
    ];
    $media = Drupal::entityTypeManager()
      ->getStorage('media')
      ->create($fields);
    $media->save();
    $targets[] = ['target_id' => $media->id()];
  }
  return $targets;
}

function convertResources($resources) {
  return array_map(function($resource) {
    return [
      'uri' => $resource->{'Resource Link'},
      'title' => $resource->{'Resource Title'}
    ];
  }, array_filter($resources, function($resource) {
    return !empty($resource->{'Resource Link'}) && !empty($resource->{'Resource Title'});
  }));
}
