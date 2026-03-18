<?php

/**
 * Generate JSONL from GatherContent items.
 *
 * Usage: drush scr scripts/migration/gc-jsonl -- [--status "Status name"] [--item itemId] projectId
 */

$email = getenv('GATHERCONTENT_EMAIL');
$apiKey = getenv('GATHERCONTENT_APIKEY');

$getopt = new \GetOpt\GetOpt([
  ['h', 'help', \GetOpt\GetOpt::NO_ARGUMENT, 'Show this help and quit'],
  ['s', 'status', \GetOpt\GetOpt::MULTIPLE_ARGUMENT, 'Item statuses to download (can be repeated)'],
  ['i', 'item', \GetOpt\GetOpt::MULTIPLE_ARGUMENT, 'Item identifiers to download (can be repeated)']
], [\GetOpt\GetOpt::SETTING_STRICT_OPERANDS => true]);
$getopt->addOperand(new \GetOpt\Operand('projectId', \GetOpt\Operand::REQUIRED));
try {
  $getopt->process($extra);
}
catch (Exception $e) {
  die($getopt->getHelpText());
}

// Prepare GatherContent API headers.
$email = getenv('GATHERCONTENT_EMAIL');
$apiKey = getenv('GATHERCONTENT_APIKEY');
$projectId = $getopt->getOperand('projectId');
$client = new \GuzzleHttp\Client(['base_uri' => 'https://api.gathercontent.com/']);
$headers = [
  'accept' => 'application/vnd.gathercontent.v2+json',
  'authorization' => 'Basic ' . base64_encode($email . ':' . $apiKey),
];
$headers0_5 = [
  'accept' => 'application/vnd.gathercontent.v0.5+json',
];

try {
  // Get folders.
  $folders = [];
  $response = $client->request('GET', "https://api.gathercontent.com/projects/$projectId/folders", [
    'headers' => $headers
  ]);
  $results = json_decode($response->getBody())->data;
  foreach ($results as $result) {
    $folders[$result->uuid] = $result;
  }

  // Get status ids.
  $response = $client->request('GET', "https://api.gathercontent.com/projects/$projectId/statuses", [
    'headers' => $headers0_5,
    'auth' => [$email, $apiKey],
  ]);
  $project_statuses = json_decode($response->getBody())->data;
  $download_statuses = array_map('strtolower', $getopt->getOption('status'));
  $status_ids = array_values(array_map(function ($status) {
    return $status->id;
  }, array_filter($project_statuses, function ($status) use ($download_statuses) {
    return in_array(strtolower($status->name), $download_statuses);
  })));

  // Build query filter and make initial request.
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
  $response = $client->request('GET', "https://api.gathercontent.com/projects/$projectId/items", [
    'headers' => $headers,
    'query' => $filter,
  ]);
  $results = json_decode($response->getBody())->data;

  // Loop on the item list and build the JSON structure.
  $templates = [];
  foreach ($results as $key => $result) {
    $response = $client->request('GET', "https://api.gathercontent.com/items/{$result->id}", [
      'headers' => $headers,
    ]);
    $item = json_decode($response->getBody())->data;

    // Cache the item template.
    if (empty($item->template_id)) {
      continue;
    }
    if (!array_key_exists($item->template_id, $templates)) {
      $response = $client->request('GET', "https://api.gathercontent.com/templates/{$item->template_id}", [
        'headers' => $headers,
      ]);
      $template = json_decode($response->getBody());

      $templates[$item->template_id] = map_fields_ids($template);
      $templates[$item->template_id]['template'] = $template->data;
    }

    // Loop on the content fields and translate field ids to field labels.
    $item_status = array_filter($project_statuses, function ($status) use ($item) {
      return $status->id == $item->status_id;
    });
    $content = [
      'title' => $item->name,
      'id' => $item->id,
      'template' => $templates[$item->template_id]['template']->name,
      'folder' => $folders[$item->folder_uuid]?->name,
      'status' => current($item_status)->name,
    ];
    foreach ($item->content as $uuid => $value) {
      $field = $templates[$item->template_id][$uuid];
      // In case the field is a component, loop again on all component fields.
      if ($field->field_type === 'component') {
        // If the keys are strings, that means it's a single object: stuff it in a real array.
        if (is_object($value) && gettype(current(array_keys(get_object_vars($value)))) === 'string') {
          $value = [$value];
        }
        foreach ($value as $i => $component) {
          if (is_array($component) || is_object($component)) {
            $entry = [];
            foreach ($component as $component_uuid => $component_value) {
              $component_field = $templates[$item->template_id][$component_uuid];
              $entry[$component_field->label] = extract_value($component_value);
            }
            $content[$field->label][] = $entry;
          }
          else {
            $component_field = $templates[$item->template_id][$i];
            $content[$field->label][][$component_field->label] = extract_value($component);
          }
        }
      }
      else {
        $content[$field->label] = extract_value($value);
      }
    }
    print(json_encode($content) . PHP_EOL);
  }
}
catch (Exception $e) {
    die('ERROR: ' . $e->getMessage() . PHP_EOL);
}

/**
 * Create a map of field ids to labels.
 */
function map_fields_ids($template) {
  $map = [];
  foreach ($template->related->structure->groups as $group) {
    foreach ($group->fields as $field) {
      $map[$field->uuid] = $field;
      if ($field->field_type === 'component') {
        foreach ($field->component->fields as $component_field) {
          $map[$component_field->uuid] = $component_field;
        }
      }
    }
  }
  return $map;
}

/**
 * Extract value.
 */
function extract_value($value) {
  if (is_string($value)) {
    return htmlspecialchars_decode(trim($value));
  }
  else if (is_array($value)) {
    return array_map('extract_value', $value);
  }
  return $value;
}
