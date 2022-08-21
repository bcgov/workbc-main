<?php

require('gc-drupal.php');

/**
 * Usage: drush scr scripts/migration/video_library
 *
 * Revert: drush entity:delete media --bundle=remote_video
 */

// Load category terms to use later.
$terms = [];
foreach (\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('video_categories') as $term) {
    $terms[$term->name] = $term->tid;
}

// Insert all videos in the media library.
$video_library = fopen(__DIR__ . '/data/video_library.jsonl', 'r');
while (!feof($video_library)) {
    $video = json_decode(fgets($video_library));
    if (empty($video)) continue;
    print("Importing $video->title...\n");
    convertVideo($video->original_url, [
        'field_duration' => ['duration' => convertDuration($video->duration), 'seconds' => $video->duration],
        'field_description' => convertDescription($video->description),
        'field_category' => [['target_id' => convertCategory($video->title, $terms)]],
    ]);
}

function convertDuration($seconds) {
    return sprintf('PT%02dM%02dS', $seconds / 60 % 60, $seconds % 60);
}

function convertCategory($title, &$terms) {
    if (stripos($title, 'Episode') === false) return NULL;
    $term_map = [
        'c' => 'Careers A - C',
        'g' => 'Careers D - G',
        'm' => 'Careers H - M',
        'r' => 'Careers N - R',
        'z' => 'Careers S - Z',
    ];
    foreach ($term_map as $c => $t) {
        if (strncasecmp($title, $c, 1) <= 0) {
            return $terms[$t];
        }
    }
    print("  Could not find category term");
    return NULL;
}

function convertDescription($description) {
    $paragraphs = explode("\n\n", $description);
    return convertRichText($paragraphs[0]);
}
