<?php

$email = $_ENV['GATHERCONTENT_EMAIL'];
$apiKey = $_ENV['GATHERCONTENT_APIKEY'];

$getopt = new \GetOpt\GetOpt([
  ['h', 'help', \GetOpt\GetOpt::NO_ARGUMENT, 'Show this help and quit']
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
  $statuses = $gc->projectStatusesGet($getopt->getOperand('id'));
  $publish_status = current(array_filter($statuses['data'], function ($status) {
    return strcasecmp($status->name, 'publish') === 0;
  }))->id;
  $results = $gc->itemsGet($getopt->getOperand('id'), ['status_id' => [$publish_status]]);
  $templates = [];
  $items = [];
  foreach ($results['data'] as $i) {
    $item = $gc->itemGet($i->id);
    if (!array_key_exists($item->templateId, $templates)) {
      $templates[$item->templateId] = map_fields_uuids($gc->templateGet($item->templateId));
    }
    $content = [];
    foreach ($item->content as $uuid => $value) {
      $content[$templates[$item->templateId][$uuid]->label] = $value;
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
    }
  }
  return $map;
}
