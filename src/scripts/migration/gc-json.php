<?php

$email = $_ENV['GATHERCONTENT_EMAIL'];
$apiKey = $_ENV['GATHERCONTENT_APIKEY'];

const PUBLISHED_STATUS = 'publish';

$getopt = new \GetOpt\GetOpt([
  ['h', 'help', \GetOpt\GetOpt::NO_ARGUMENT, 'Show this help and quit'],
  ['s', 'status', \GetOpt\GetOpt::MULTIPLE_ARGUMENT, 'Item statuses to download (can be repeated) (default: publish)', 'publish'],
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
  $project_statuses = $gc->projectStatusesGet($getopt->getOperand('id'));
  $download_statuses = array_map('strtolower', $getopt->getOption('status'));
  $status_ids = array_values(array_map(function ($status) {
    return $status->id;
  }, array_filter($project_statuses['data'], function ($status) use ($download_statuses) {
    return in_array(strtolower($status->name), $download_statuses);
  })));
  $results = $gc->itemsGet($getopt->getOperand('id'), ['status_id' => $status_ids]);
  $templates = [];
  $items = [];
  foreach ($results['data'] as $i) {
    $item = $gc->itemGet($i->id);
    if (!array_key_exists($item->templateId, $templates)) {
      $templates[$item->templateId] = map_fields_uuids($gc->templateGet($item->templateId));
    }
    $content = [];
    foreach ($item->content as $uuid => $value) {
      $field = $templates[$item->templateId][$uuid];
      if ($field->type === 'component') {
        if (isset($field->metaData['repeatable'])) {
          foreach ($value as $i => $component) {
            foreach ($component as $component_uuid => $component_value) {
              $component_field = $templates[$item->templateId][$component_uuid];
              $content[$field->label][$i][$component_field->label] = $component_value;
            }
          }
        }
        else {
          foreach ($value as $component_uuid => $component_value) {
            $component_field = $templates[$item->templateId][$component_uuid];
            $content[$field->label][$i][$component_field->label] = $component_value;
          }
        }
      }
      else {
        $content[$field->label] = $value;
      }
    }
    $items[] = $content;
  }
  print(json_encode($items, JSON_PRETTY_PRINT));
}
catch (Exception $e) {
    die('ERROR: ' . $e->getMessage() . PHP_EOL);
}

function map_fields_uuids($template) {
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
