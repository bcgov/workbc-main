<?php

use Drupal\workbc_custom\Form\SsotUploadLmmuForm;

fputcsv(STDOUT, ['cell', 'column', 'rule']);
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
  if (array_key_exists('related', $validation)) {
    fputcsv(STDOUT, [
      $form->validations[$validation['related']]['cell'] . ', ' . $validation['cell'],
      $key,
      $form->descriptions['related']
    ]);
  }
}
