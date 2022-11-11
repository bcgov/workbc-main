<?php

require('utilities.php');

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Generate career profile nodes from SSoT entries and optional career_profiles.jsonl import from GatherContent.
 * Sources:
 * - SSoT /wages (WorkBC_2021_Wage_Data)
 * - GC WorkBC Career Profiles (scripts/migration/data/career_profiles.jsonl)
 *
 * Usage: drush scr scripts/migration/career_profiles
 *
 */

// Read and migrate GatherContent career profile introduction if present.
$career_profile_introductions = NULL;
if (file_exists(__DIR__ . '/data/career_profile_introductions.jsonl')) {
  print("Reading GC Career Profile Introductions" . PHP_EOL);
  $item = json_decode(file_get_contents(__DIR__ . '/data/career_profile_introductions.jsonl'));
  $career_profile_introductions = createNode([
    'type' => 'career_profile_introductions',
    'title' => convertPlainText($item->title),
    'field_employment_introduction' => convertRichText($item->{'Employment Introduction'}),
    'field_industry_highlights_intro' => convertRichText($item->{'Industry Highlights Introduction'}),
    'field_labour_market_introduction' => convertRichText($item->{'Labour Market Outlook Introduction'}),
    'field_labour_market_statistics_i' => convertRichText($item->{'Labour Market Statistics Introduction'}),
    'field_occupational_interests_int' => convertRichText($item->{'Occupational Interests Introduction'}),
    'field_salary_introduction' => convertRichText($item->{'Salary Introduction'}),
    'field_skills_introduction' => convertRichText($item->{'Skills Introduction'}),
  ]);
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
      'title' => convertPlainText($profile['occupation_title']),
      'field_noc' => $profile['noc'],
      'uid' => 1,
      'moderation_state' => 'published',
    ];
    print("Creating {$fields['title']}\n");

    // Check GC import for introductory blurbs.
    if (!empty($career_profile_introductions)) {
      $fields = array_merge($fields, [
        'field_introductions' => ['target_id' => $career_profile_introductions->id()],
      ]);
    }

    // Check GC import for this career profile.
    if (array_key_exists($profile['noc'], $career_profiles)) {
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
      print("  Could not find a GatherContent item for this profile" . PHP_EOL);
      $career_profiles[$profile['noc']] = new stdClass();
    }

    $node = createNode($fields, 'https://www.workbc.ca/Jobs-Careers/Explore-Careers/Browse-Career-Profile/' . $profile['noc']);
    $career_profiles[$profile['noc']]->nid = $node->id();
  }

  /**
   * Second pass: Relate career profiles to each other.
   */
  foreach ($career_profiles as $noc => $career_profile) {
    if (empty($career_profile->{'Related Careers NOCs'})) continue;

    print("Relating NOC $noc to other NOCs" . PHP_EOL);
    $node = Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($career_profile->nid);
    if (empty($node)) {
      print("  Could not find node {$career_profile->nid}" . PHP_EOL);
      continue;
    }

    foreach (convertMultiline($career_profile->{'Related Careers NOCs'}) as $raw_related_noc) {
      $related_noc = NULL;
      if (!preg_match('/\d+/', $raw_related_noc, $related_noc)) {
        print("  Could not parse related NOC $raw_related_noc" . PHP_EOL);
        continue;
      }
      if (!array_key_exists($related_noc[0], $career_profiles)) {
        print(" Could not find related NOC {$related_noc[0]}" . PHP_EOL);
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
