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
$videos = json_decode(file_get_contents(__DIR__ . '/data/video_library.json'));
foreach ($videos as $video) {
    print("Importing $video->title...\n");
    convertVideo($video->original_url, [
        'field_duration' => ['duration' => convertDuration($video->duration), 'seconds' => $video->duration],
        'field_description' => convertRichText($video->description),
        'field_category' => [['target_id' => convertVideoCategory($video->title, $terms)]],
    ]);
}

function convertDuration($seconds) {
    return sprintf('PT%02dM%02dS', $seconds / 60 % 60, $seconds % 60);
}

function convertVideoCategory($title, &$terms) {
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
