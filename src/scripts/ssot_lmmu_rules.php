<?php

use Drupal\workbc_ssot\Form\SsotUploadLmmuForm;

fputcsv(STDOUT, ['CELL', 'COLUMN', 'RULE', 'ADDITIONAL CELLS']);
$form = new SsotUploadLmmuForm();
foreach ($form->validations as $key => $validation) {
  if (array_key_exists('cell', $validation)) {
    fputcsv(STDOUT, [
      $validation['cell'],
      $key,
      $form->descriptions[$validation['type']]
    ]);
  }
  if (!array_key_exists('value', $validation)) {
    fputcsv(STDOUT, [
      $validation['cell'],
      $key,
      $form->descriptions['blank']
    ]);
  }
  foreach ([
    'same_sign',
    'previous_month',
    'previous_month_change_abs',
    'previous_month_change_pct',
  ] as $validation_type) {
    if (array_key_exists($validation_type, $validation)) {
      fputcsv(STDOUT, [
        $validation['cell'],
        $key,
        $form->descriptions[$validation_type],
        $form->validations[$validation[$validation_type]]['cell']
      ]);
    }
  }
  if (array_key_exists('sum', $validation)) {
    fputcsv(STDOUT, [
      $validation['cell'],
      $key,
      $form->descriptions['sum'],
      implode(', ', array_map(function ($cells) use ($form) {
        return implode('+', array_map(function ($cell) use ($form) {
          return $form->validations[$cell]['cell'];
        }, $cells));
      }, $validation['sum']))
    ]);
  }
}
