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
  $items = $gc->itemsGet($getopt->getOperand('id'), ['status_id' => [$publish_status]]);
  $templates = [];
  foreach ($items['data'] as $i) {
    $item = $gc->itemGet($i->id);
    if (!array_key_exists($item->templateId, $templates)) {
      $templates[$item->templateId] = $gc->templateGet($item->templateId);
    }
    print($item->name . ' isA ' . $templates[$item->templateId]['data']->name . PHP_EOL);
  }
}
catch (Exception $e) {
    die('ERROR: ' . $e->getMessage() . PHP_EOL);
}
