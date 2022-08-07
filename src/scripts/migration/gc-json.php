<?php

/**
 * Generate JSON from GatherContent items.
 *
 * Usage: drush scr /scripts/migration/gc-json -- [--status "Status name"] [--item itemId] projectId
 */

$email = $_ENV['GATHERCONTENT_EMAIL'];
$apiKey = $_ENV['GATHERCONTENT_APIKEY'];

$getopt = new \GetOpt\GetOpt([
  ['h', 'help', \GetOpt\GetOpt::NO_ARGUMENT, 'Show this help and quit'],
  ['s', 'status', \GetOpt\GetOpt::MULTIPLE_ARGUMENT, 'Item statuses to download (can be repeated)'],
  ['i', 'item', \GetOpt\GetOpt::MULTIPLE_ARGUMENT, 'Item identifiers to download (can be repeated)']
], [\GetOpt\GetOpt::SETTING_STRICT_OPERANDS => true]);
$getopt->addOperand(new \GetOpt\Operand('id', \GetOpt\Operand::REQUIRED));
try {
  $getopt->process($extra);
}
catch (Exception $e) {
  die($getopt->getHelpText());
}

$client = new \GuzzleHttp\Client();
$gc = new \Cheppers\GatherContent\GatherContentClient($client);
$gc
  ->setEmail($email)
  ->setApiKey($apiKey);
try {
  // Build query filter and make initial request.
  $project_statuses = $gc->projectStatusesGet($getopt->getOperand('id'));
  $download_statuses = array_map('strtolower', $getopt->getOption('status'));
  $status_ids = array_values(array_map(function ($status) {
    return $status->id;
  }, array_filter($project_statuses['data'], function ($status) use ($download_statuses) {
    return in_array(strtolower($status->name), $download_statuses);
  })));
  $filter = [
    'per_page' => 500,
  ];
  if (!empty($status_ids)) {
    $filter['status_id'] = $status_ids;
  }
  if (!empty($getopt->getOption('item'))) {
    $filter['item_id'] = $getopt->getOption('item');
  }
  // TODO Pagination.
  $results = $gc->itemsGet($getopt->getOperand('id'), $filter);

  // Loop on the item list and build the JSON structure.
  $templates = [];
  $last_key = end(array_keys($results['data']));
  print("[\n");
  foreach ($results['data'] as $key => $result) {
    $item = $gc->itemGet($result->id);

    // Cache the item template.
    if (!array_key_exists($item->templateId, $templates)) {
      $templates[$item->templateId] = map_fields_ids($gc->templateGet($item->templateId));
    }

    // Loop on the content fields and translate field ids to field labels.
    $content = [
      'title' => $item->name,
      'id' => $item->id,
    ];
    foreach ($item->content as $uuid => $value) {
      $field = $templates[$item->templateId][$uuid];
      // In case the field is a component, loop again on all component fields.
      if ($field->type === 'component') {
        foreach ($value as $i => $component) {
          if (is_array($component) || is_object($component)) {
            $entry = [];
            foreach ($component as $component_uuid => $component_value) {
              $component_field = $templates[$item->templateId][$component_uuid];
              $entry[$component_field->label] = $component_value;
            }
            $content[$field->label][] = $entry;
          }
          else {
            $component_field = $templates[$item->templateId][$i];
            $content[$field->label][][$component_field->label] = $component;
          }
        }
      }
      else {
        $content[$field->label] = $value;
      }
    }
    print(json_encode($content, JSON_PRETTY_PRINT));
    if ($key !== $last_key) {
      print(',');
    }
    print("\n");
  }
  print("]\n");
}
catch (Exception $e) {
    die('ERROR: ' . $e->getMessage() . PHP_EOL);
}

/**
 * Create a map of field ids to labels.
 */
function map_fields_ids($template) {
  $map = [];
  foreach ($template['related']->structure->groups as $group) {
    foreach ($group->fields as $field) {
      $map[$field->id] = $field;
      if ($field->type === 'component') {
        foreach ($field->component->fields as $component_field) {
          $map[$component_field->id] = $component_field;
        }
      }
    }
  }
  return $map;
}
