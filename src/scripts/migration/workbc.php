<?php

require('gc-drupal.php');

use Drupal\paragraphs\Entity\Paragraph;

/**
 * Update nodes for GatherContent WorkBC items.
 *
 * Usage: drush scr scripts/migration/workbc [--item itemId]
 *
 */

// Accept an option to import a single item given its id
$getopt = new \GetOpt\GetOpt([
    ['i', 'item', \GetOpt\GetOpt::REQUIRED_ARGUMENT, 'Item identifier to import'],
], []);
try {
    $getopt->process($extra);
}
catch (Exception $e) {
    die($getopt->getHelpText() . PHP_EOL);
}
$item_id = trim($getopt->getOption('item'));

// Read GatherContent labour market introductions if present.
$labour_market_introductions = NULL;
if (file_exists(__DIR__ . '/data/labour_market_introductions.jsonl')) {
    print("Reading GC Labour Market Introductions" . PHP_EOL);
    // There's no "labour_market_introductions" content type,
    // because there's only a single "labour_market" instance,
    // so the fields will be directly copied into this instance when it's created.
    $labour_market_introductions = json_decode(file_get_contents(__DIR__ . '/data/labour_market_introductions.jsonl'));
}

// Read GatherContent industry profile introductions if present.
$industry_profile_introductions = NULL;
if (file_exists(__DIR__ . '/data/industry_profile_introductions.jsonl')) {
    print("Reading GC Industry Profile Introductions" . PHP_EOL);
    $item = json_decode(file_get_contents(__DIR__ . '/data/industry_profile_introductions.jsonl'));
    $industry_profile_introductions = createNode([
        'type' => 'industry_profile_introductions',
        'title' => convertPlainText($item->title),
        'field_employment_introduction' => convertRichText($item->{'Employment Introduction'}),
        'field_hourly_earnings_introducti' => convertRichText($item->{'Hourly Earnings Introduction'}),
        'field_labour_market_introduction' => convertRichText($item->{'Labour Market Outlook Introduction'}),
        'field_labour_market_statistics_i' => convertRichText($item->{'Labour Market Statistics Introduction'}),
        'field_top_occupations_by_number_' => convertRichText($item->{'Top Occupations by Number of Job Openings Introduction'}),
        'field_workforce_introduction' => convertRichText($item->{'Workforce Introduction'}),
    ]);
}

// Read GatherContent regional profile introductions if present.
$regional_profile_introductions = NULL;
if (file_exists(__DIR__ . '/data/regional_profile_introductions.jsonl')) {
    print("Reading GC Regional Profile Introductions" . PHP_EOL);
    $item = json_decode(file_get_contents(__DIR__ . '/data/regional_profile_introductions.jsonl'));
    $regional_profile_introductions = createNode([
        'type' => 'region_profile_introductions',
        'title' => convertPlainText($item->title),
        'field_labour_market_statistics_i' => convertRichText($item->{'Labour Market Statistics Introduction'}),
        'field_employment_introduction' => convertRichText($item->{'Employment Introduction'}),
        'field_labour_market_introduction' => convertRichText($item->{'Labour Market Outlook Introduction'}),
        'field_top_occupations_introducti' => convertRichText($item->{'Top Occupations Introduction'}),
    ]);
}

// Read GatherContent page data.
$file = __DIR__ . '/data/workbc.jsonl';
if (($data = fopen($file, 'r')) === FALSE) {
    die("Could not open GC WorkBC items $file" . PHP_EOL);
}
print("Importing GC WorkBC items $file" . PHP_EOL);

$items = [];
while (!feof($data)) {
    $item = json_decode(fgets($data));
    if (empty($item)) continue;
    $item->process = TRUE;

    // SPECIAL CASES
    // There are two "Reports" pages. For one of them, its parent item in IA is different than its parent folder in GC - which makes it unidentifiable.
    if (strcasecmp($item->title, 'Reports') === 0 && strcasecmp($item->folder, 'Reports') === 0) {
        $item->folder = 'B.C.’s Economy';
    }

    // "Register" is really the "Account" page.
    if (strcasecmp($item->title, 'Register') === 0) {
        $item->title = 'Account';
    }

    $items[$item->id] = $item;
}

// FIRST PASS: Create nodes that are not expressed in the IA.
print("FIRST PASS =================" . PHP_EOL);
foreach ($items as $id => $item) {
    if (!empty($item_id) && $id != $item_id) continue;

    $title = convertPlainText($item->title);
    print("Querying \"$title\"..." . PHP_EOL);

    $node = loadNodeByTitleParent($title, $item->folder);
    if (empty($node)) {
        print("  Could not find node. Attempting to create it..." . PHP_EOL);
        $node = createItem($item);
        if (empty($node)) {
            print("  Error: Could not create Drupal node" . PHP_EOL);
            continue;
        }
    }
}

// SECOND PASS: Populate fields.
print("SECOND PASS =================" . PHP_EOL);
foreach ($items as $id => $item) {
    if (!empty($item_id) && $id != $item_id) continue;
    if (!$item->process) continue;

try {
    $title = convertPlainText($item->title);
    print("Processing \"$title\"..." . PHP_EOL);

    $fields = [];

    // Populate standard fields.
    if (property_exists($item, 'Page Content')) {
        $fields['body'] = convertRichText($item->{'Page Content'}, $items);
    }

    if (property_exists($item, 'Page Description')) {
        $fields['field_hero_text'] = convertRichText($item->{'Page Description'}, $items);
    }

    if (property_exists($item, 'Related Topics Blurb')) {
        $fields['field_related_topics_blurb'] = convertRichText($item->{'Related Topics Blurb'}, $items);
    }

    if (property_exists($item, 'Banner Image')) {
        $images = array_map('convertImage', array_filter($item->{'Banner Image'}));
        if (!empty($images)) {
            $fields['field_hero_image'] = current($images);
            $fields['field_image'] = current($images);
        }
    }

    // Add related items which come in 2 flavours.
    if (property_exists($item, 'Related Topics Card')) {
        $fields['field_related_topics'] = convertRelatedTopics($item->{'Related Topics Card'}, $items);
    }
    if (property_exists($item, 'Related Topics Link Target')) {
        $fields['field_related_topics'] = convertRelatedTopics(array_map(function($target) {
            $card = new stdClass();
            $card->{'Link Target'} = $target;
            return $card;
        }, array_filter($item->{'Related Topics Link Target'})), $items);
    }

    // Import all variations of cards.
    $field_content = [];
    $container_paragraph = NULL;
    foreach([
        'Card' => NULL,
        'CTA - Feature' => 'Feature',
        'CTA - Full' => 'Full Width',
        'CTA - 1/2' => '1/2 Width',
        'CTA - 1/3' => '1/3 Width',
        'Quote' => 'Quote',
    ] as $card_field => $card_type) {
        if (property_exists($item, $card_field)) {
            $field_content = array_merge($field_content, convertCards($item->$card_field, $card_type, $items, $container_paragraph));
        }
    }
    if (!empty($field_content)) {
        $fields['field_content'] = $field_content;
    }

    // Populate remaining fields based on specific conditions.
    $template = convertPlainText($item->template);
    if ($template === 'Blog Post, News Post, Success Stories Post') {
        if (property_exists($item, 'Date')) {
            $fields['published_date'] = strtotime($item->{'Date'});
        }
    }
    else if ($title === 'Labour Market Monthly Update' && !empty($labour_market_introductions)) {
        if (property_exists($labour_market_introductions, 'Employment Introduction')) {
            $fields['field_employment_introduction'] = convertRichText($labour_market_introductions->{'Employment Introduction'});
        }
        if (property_exists($labour_market_introductions, 'Industry Highlights Introduction')) {
            $fields['field_industry_highlights_intro'] = convertRichText($labour_market_introductions->{'Industry Highlights Introduction'});
        }
        if (property_exists($labour_market_introductions, 'Unemployment by Region Introduction')) {
            $fields['field_unemployment_region_intro'] = convertRichText($labour_market_introductions->{'Unemployment by Region Introduction'});
        }
        if (property_exists($labour_market_introductions, 'Unemployment Introduction')) {
            $fields['field_unemployment_introduction'] = convertRichText($labour_market_introductions->{'Unemployment Introduction'});
        }
    }
    else if ($template === 'Industry Profile') {
        if (property_exists($item, 'Industry Overview')) {
            $fields['field_industry_overview'] = convertRichText($item->{'Industry Overview'}, $items);
        }
        if (property_exists($item, 'Key Facts')) {
            $fields['field_key_facts'] = convertRichText($item->{'Key Facts'}, $items);
        }
        if (property_exists($item, 'Resource')) {
            $fields['field_resources'] = convertResources($item->{'Resource'});
        }
        if (!empty($industry_profile_introductions)) {
            $fields['field_introductions'] = ['target_id' => $industry_profile_introductions->id()];
        }
    }
    else if ($template === 'Regional Profile') {
        if (!empty($regional_profile_introductions)) {
            $fields['field_introductions'] = ['target_id' => $regional_profile_introductions->id()];
        }
    }

    $node = loadNodeByTitleParent($title, $item->folder);
    if (empty($node)) {
        print("  Error: Could not find node" . PHP_EOL);
        continue;
    }
    foreach ($fields as $field => $value) {
        $node->$field = $value;
    }
    $node->setPublished(TRUE);
    $node->save();
}
catch (Exception $e) {
    print("  Error: Could not save node: " . $e->getMessage() . PHP_EOL);
}

}

function convertRelatedTopics($related_topics, &$items) {
    $field = [];
    if (!empty($related_topics)) foreach (array_filter($related_topics, function($card) {
        return !empty($card->{'Link Target'});
    }) as $card) {
        $related_items = convertGatherContentLinks($card->{'Link Target'}, $items);
        if (empty($related_items)) {
            print("  Could not parse related GatherContent item {$card->{'Link Target'}}" . PHP_EOL);
            continue;
        }
        $field[] = ['target_id' => current($related_items)['target_id']];
    }
    return $field;
}

function createItem($item) {
    switch (convertPlainText($item->template)) {
        case 'Blog Post, News Post, Success Stories Post':
            return createBlogNewsSuccessStory($item);
        case 'Industry Profile':
            return createIndustryProfile($item);
        case 'Regional Profile':
            return createRegionalProfile($item);
        default:
            break;
    }
    return NULL;
}

function createRegionalProfile($item) {
    $title = convertPlainText($item->title);
    $type = strcasecmp($title, "British Columbia") === 0 ? 'bc_profile' : 'region_profile';
    return createNode([
        'type' => $type,
        'title' => $title,
    ]);
}

function createIndustryProfile($item) {
    return createNode([
        'type' => 'industry_profile',
        'title' => convertPlainText($item->title),
    ]);
}

function createBlogNewsSuccessStory($item) {
    switch ($item->folder) {
        case 'Blog':
            $type = 'blog';
            break;
        case 'News':
            $type = 'news';
            break;
        case 'Success Stories':
            $type = 'success_story';
            break;
        default:
            print("  Unhandled folder {$item->folder} for blogs/news/success stories. Ignoring" . PHP_EOL);
            return;
    }
    return createNode([
        'type' => $type,
        'title' => convertPlainText($item->title),
    ]);
}

function convertCards($cards, $card_type, &$items, &$container_paragraph) {
    $card_types = [
        'Feature' => [
            'container' => 'action_card_feature',
            'card' => 'action_card',
            'field_name' => 'field_action_card',
            'only_one_card_per_container' => TRUE,
        ],
        'Full Width' => [
            'container' => 'action_cards_full_width',
            'card' => 'action_card_full_width',
            'field_name' => 'field_action_cards',
            'only_one_card_per_container' => FALSE,
        ],
        '1/2 Width' => [
            'container' => 'action_cards_1_2',
            'card' => 'action_card',
            'field_name' => 'field_action_cards',
            'only_one_card_per_container' => FALSE,
        ],
        '1/3 Width' => [
            'container' => 'action_cards_1_3',
            'card' => 'action_card',
            'field_name' => 'field_action_cards',
            'only_one_card_per_container' => FALSE,
        ],
        '1/4 Width' => [
            'container' => 'action_cards_1_4',
            'card' => 'action_card',
            'field_name' => 'field_action_cards',
            'only_one_card_per_container' => FALSE,
        ],
        'Quote' => [
            'container' => 'action_cards_1_3',
            'card' => 'quote_card',
            'field_name' => 'field_action_cards',
            'only_one_card_per_container' => FALSE,
        ],
        'Full Width Icon' => [
            'container' => 'action_cards_icon',
            'card' => 'action_card_icon',
            'field_name' => 'field_action_cards',
            'only_one_card_per_container' => FALSE,
        ],
        '1/2 Width Icon' => [
            'container' => 'action_cards_icon',
            'card' => 'action_card_icon',
            'field_name' => 'field_action_cards',
            'only_one_card_per_container' => FALSE,
        ],
        'Explore Careers > Career Tools' => [
            'container' => 'action_cards_explore_careers',
            'card' => 'action_card',
            'field_name' => 'field_action_cards',
            'only_one_card_per_container' => FALSE,
        ],
        'Explore Careers > Featured Resources' => [
            'container' => 'action_cards_icon',
            'card' => 'action_card_icon',
            'field_name' => 'field_action_cards',
            'only_one_card_per_container' => FALSE,
        ],
        'Explore Careers > Additional Topics' => [
            'container' => 'action_cards_1_4',
            'card' => 'action_card',
            'field_name' => 'field_action_cards',
            'only_one_card_per_container' => FALSE,
        ],
        'Not a card, just a block of content (in the Body field below)' => [
            'container' => 'content_text',
            'card' => NULL,
            'field_name' => 'field_body',
            'only_one_card_per_container' => TRUE,
        ],
    ];

    $paragraphs = [];
    foreach ($cards as $card) {
        $empty = TRUE;
        foreach (['Card Type', 'Title', 'Body', 'Image', 'Link Text', 'Link Target'] as $check) {
            if (property_exists($card, $check) && !empty($card->$check)) $empty = FALSE;
        }
        if ($empty) continue;

        $type = property_exists($card, 'Card Type') && !empty($card->{'Card Type'}) ? convertRadio($card->{'Card Type'}) : $card_type;
        if (empty($type)) {
            print("  Found a card with empty type. Assuming Full Width" . PHP_EOL);
            $type = 'Full Width';
        }
        if (!array_key_exists($type, $card_types)) {
            print("  Error: Cannot create container with unknown type $type" . PHP_EOL);
            continue;
        }

        // Create new container if needed.
        if (empty($container_paragraph) || $card_types[$type]['only_one_card_per_container'] || $card_types[$type]['container'] !== $container_paragraph->bundle()) {
            $container_paragraph = Paragraph::create([
                'type' => $card_types[$type]['container'],
                'uid' => 1,
            ]);
            $container_paragraph->isNew();
            $container_paragraph->save();

            $paragraphs[] = [
                'target_id' => $container_paragraph->id(),
                'target_revision_id' => $container_paragraph->getRevisionId(),
            ];
        }

        // Populate container.
        if (empty($card_types[$type]['card'])) {
            $container_paragraph->set($card_types[$type]['field_name'], convertRichText($card->{'Body'}));
        }
        else {
            // Create card and add it to container.
            $card_fields = [
                'type' => $card_types[$type]['card'],
                'uid' => 1,
            ];
            if (property_exists($card, 'Title')) {
                $card_fields['field_title'] = convertPlainText($card->{'Title'});
                $card_fields['field_author'] = convertPlainText($card->{'Title'});
            }
            if (property_exists($card, 'Body')) {
                $card_fields['field_description'] = convertRichText($card->{'Body'});
                $card_fields['field_quote'] = convertRichText($card->{'Body'});
            }
            if (property_exists($card, 'Image')) {
                if ($card_types[$type]['card'] === 'action_card_icon') {
                    $icons = array_map('convertIcon', array_filter($card->{'Image'}));
                    if (!empty($icons)) {
                        $card_fields['field_icon'] = current($icons);
                    }
                }
                else {
                    $images = array_map('convertImage', array_filter($card->{'Image'}));
                    if (!empty($images)) {
                        $card_fields['field_image'] = current($images);
                    }
                }
            }
            if (property_exists($card, 'Link Text') && property_exists($card, 'Link Target')) {
                $link_text = convertPlainText($card->{'Link Text'});
                $link_target = convertPlainText($card->{'Link Target'});
                if (!empty($link_text) && !empty($link_target)) {
                    $card_fields['field_link'] = convertLink($link_text, $link_target, $items);
                }
            }

            $card_paragraph = Paragraph::create($card_fields);
            $card_paragraph->isNew();
            $card_paragraph->save();

            $container_paragraph->{$card_types[$type]['field_name']}[] = [
                'target_id' => $card_paragraph->id(),
                'target_revision_id' => $card_paragraph->getRevisionId(),
            ];
        }
        $container_paragraph->save();

    }
    return $paragraphs;
}
