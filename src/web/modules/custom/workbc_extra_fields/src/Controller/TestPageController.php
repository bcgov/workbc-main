<?php
namespace Drupal\workbc_extra_fields\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides route responses for the Example module.
 */
class TestPageController extends ControllerBase {

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function ssotFormatNumberTestPage() {

    $numbers = [234.5345, -234.6525, 0, NULL];
    
    $content = "";

    
    $header = [
      'value' => 'Value',
      'options' => 'Options',
      'result' => 'Result'
    ];

    $rows = [];

    $options = array(
      'decimals' => 2,
    );
    foreach ($numbers as $number) {
      $rows[] = $this->createRow($number, $options);
    }

    $options = array(
      'decimals' => 0,
    );
    foreach ($numbers as $number) {
      $rows[] = $this->createRow($number, $options);
    }

    $options = array(
      'decimals' => 2,
      'positive_sign' => TRUE,
    );
    foreach ($numbers as $number) {
      $rows[] = $this->createRow($number, $options);
    }

    $options = array(
      'decimals' => 2,
      'positive_sign' => TRUE,
      'suffix' => "%",
    );
    foreach ($numbers as $number) {
      $rows[] = $this->createRow($number, $options);
    }

    $options = array(
      'decimals' => 2,
      'positive_sign' => TRUE,
      'prefix' => "$",
    );
    foreach ($numbers as $number) {
      $rows[] = $this->createRow($number, $options);
    }

    $options = array(
      'decimals' => 2,
      'positive_sign' => TRUE,
      'no_negative' => TRUE,
    );
    foreach ($numbers as $number) {
      $rows[] = $this->createRow($number, $options);
    }

    $options = array(
      'decimals' => 2,
      'positive_sign' => TRUE,
      'na_if_empty' => TRUE,
    );
    foreach ($numbers as $number) {
      $rows[] = $this->createRow($number, $options);
    }    

    $options = array(
      'decimals' => 2,
      'positive_sign' => TRUE,
      'na_if_empty' => TRUE,      
      'sanity_check' => TRUE,
    );
    foreach ($numbers as $number) {
      $rows[] = $this->createRow($number, $options);
    }

    $options = array(
      'decimals' => 1,
      'nap_if_empty' => TRUE,      
      'sanity_check' => TRUE,
    );
    foreach ($numbers as $number) {
      $rows[] = $this->createRow($number, $options);
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => t('No content has been found.'),
    ];


    return [
      '#markup' => render($build),
    ];
  }


  function createRow($number, $options) {
    $result = ssotFormatNumber($number, $options);
    $info = json_encode($options);
    $value = is_null($number) ? "null" : strval($number);
    $row = [
      ['data' => $value, 'align' => 'right'],
      ['data' => $info],
      ['data' => $result, 'align' => 'right'],
    ];
    return $row;

  }
}