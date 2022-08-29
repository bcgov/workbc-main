<?php

require('gc-drupal.php');

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Generate career profile nodes from SSoT entries and optional career_profiles.jsonl import from GatherContent.
 * Sources:
 * - SSoT /wages (WorkBC_2021_Wage_Data)
 * - GC WorkBC Career Profiles (scripts/migration/data/career_profiles.jsonl)
 *
 * Usage:
 * - drush scr scripts/migration/gc-jsonl -- -s publish 290255 > scripts/migration/data/career_profiles.jsonl
 * - drush scr scripts/migration/gc-jsonl -- 332842 > scripts/migration/data/career_profile_introductions.jsonl
 * - drush scr scripts/migration/career_profiles
 *
 * Revert:
 * - drush entity:delete node --bundle=career_profile
 * - drush entity:delete node --bundle=career_profile_introductions
 */

// Read and migrate GatherContent career profile introduction if present.
$career_profile_introductions = NULL;

if (file_exists(__DIR__ . '/data/career_profile_introductions.jsonl')) {
  print("Reading GC Career Profile Introductions\n");
  $career_profile_introductions = json_decode(file_get_contents(__DIR__ . '/data/career_profile_introductions.jsonl'));

  $fields = [
    'type' => 'career_profile_introductions',
    'title' => $career_profile_introductions->title,
    'uid' => 1,
    'field_employment_introduction' => convertRichText($career_profile_introductions->{'Employment Introduction'}),
    'field_industry_highlights_intro' => convertRichText($career_profile_introductions->{'Industry Highlights Introduction'}),
    'field_labour_market_introduction' => convertRichText($career_profile_introductions->{'Labour Market Outlook Introduction'}),
    'field_labour_market_statistics_i' => convertRichText($career_profile_introductions->{'Labour Market Statistics Introduction'}),
    'field_occupational_interests_int' => convertRichText($career_profile_introductions->{'Occupational Interests Introduction'}),
    'field_salary_introduction' => convertRichText($career_profile_introductions->{'Salary Introduction'}),
    'field_skills_introduction' => convertRichText($career_profile_introductions->{'Skills Introduction'}),
  ];
  $node = Drupal::entityTypeManager()
    ->getStorage('node')
    ->create($fields);
  $node->save();
  $career_profile_introductions->nid = $node->id();
}

// Read GatherContent career profiles if present.
$career_profiles = [];
if ($data = fopen(__DIR__ . '/data/career_profiles.jsonl', 'r')) {
  print("Reading GC Career Profiles\n");
  while (!feof($data)) {
    $career_profile = json_decode(fgets($data));
    if (empty($career_profile)) continue;
    $noc = NULL;
    if (!preg_match('/\d+/', $career_profile->NOC, $noc)) {
      $i = count($career_profile) + 1;
      die("Could not find NOC in record $i of career_profiles.jsonl. Aborting!" . PHP_EOL);
    }
    $career_profiles[$noc[0]] = $career_profile;
  }
}

$ssot = rtrim(\Drupal::config('workbc')->get('ssot_url'), '/');
$client = new Client();
try {
  /**
   * First pass: Create career profile nodes.
   */
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

    // Check GC import for introductory blurbs.
    if (!empty($career_profile_introductions?->nid)) {
      $fields = array_merge($fields, [
        'field_introductions' => ['target_id' => $career_profile_introductions->nid],
      ]);
    }

    // Check GC import for this career profile.
    if (array_key_exists($profile['noc'], $career_profiles)) {
      print("  Found a GatherContent record for this profile\n");

      $career_profile = $career_profiles[$profile['noc']];
      $fields = array_merge($fields, [
        'field_career_overview' => convertRichText($career_profile->{'Career Overview Content'}),
        'field_career_pathways' => convertRichText($career_profile->{'Career Pathways Content'}),
        'field_career_videos' => array_map('convertVideo', convertMultiline($career_profile->{'Career Video URLs'})),
        'field_career_videos_introduction' => convertRichText($career_profile->{'Career Videos Content'}),
        'field_duties' => convertRichText($career_profile->{'Duties Content'}),
        'field_education_training_skills' => convertRichText($career_profile->{'Education, Training and Skills Content'}),
        'field_education_programs' => convertRichText($career_profile->{'Education Programs in B.C. Content'}),
        'field_hero_image' => array_map('convertImage', array_filter($career_profile->{'Banner Image'}))[0] ?? NULL,
        'field_insights_from_industry' => convertRichText($career_profile->{'Insights from Industry Content'}),
        'field_job_titles' => convertMultiline($career_profile->{'Job Titles List'}),
        'field_resources' => convertResources($career_profile->{'Resources'}),
        'field_work_environment' => convertRichText($career_profile->{'Work Environment Content'}),
      ]);
    }
    else {
      $career_profiles[$profile['noc']] = new stdClass();
    }

    $node = Drupal::entityTypeManager()
      ->getStorage('node')
      ->create($fields);
    $node->save();

    // Save the node id for the second pass.
    $career_profiles[$profile['noc']]->nid = $node->id();
  }

  /**
   * Second pass: Relate career profiles to each other.
   */
  foreach ($career_profiles as $noc => $career_profile) {
    if (empty($career_profile->{'Related Careers NOCs'})) continue;

    print("Relating NOC $noc to other NOCs\n");
    $node = Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($career_profile->nid);
    if (empty($node)) {
      print("  Node {$career_profile->nid} not found\n");
      continue;
    }

    foreach (convertMultiline($career_profile->{'Related Careers NOCs'}) as $raw_related_noc) {
      $related_noc = NULL;
      if (!preg_match('/\d+/', $raw_related_noc, $related_noc)) {
        print("  Could not parse related NOC $raw_related_noc\n");
        continue;
      }
      if (!array_key_exists($related_noc[0], $career_profiles)) {
        print(" Could not find related NOC {$related_noc[0]}\n");
        continue;
      }

      $node->field_related_careers[] = ['target_id' => $career_profiles[$related_noc[0]]->nid];
    }

    $node->save();
  }
}
catch (RequestException $e) {
  print($e->getMessage());
}

function convertResources($resources) {
  return array_map(function($resource) {
    $uri = $resource->{'Resource Link'};
    $uri = strpos($uri, 'http') !== 0 ? "https://$uri" : $uri;
    return [
      'uri' => $uri,
      'title' => $resource->{'Resource Title'}
    ];
  }, array_filter($resources, function($resource) {
    return !empty($resource->{'Resource Link'}) && !empty($resource->{'Resource Title'});
  }));
}
