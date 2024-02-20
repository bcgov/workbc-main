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
      'positive_sign' => TRUE,
      'suffix' => "%",
      'nap_if_empty' => TRUE,      
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


  public function chartTestPage() {

    $data = array();
    $data[] = 1.3;
    $data[] = 2.7;
    $date1 = '2023-2028';
    $date2 = '2028-2033';
    $dates[] = $date1;
    $dates[] = $date2;

    $chart = [
      '#chart_id' => 'test-page-chart',
      '#type' => 'chart',
      '#chart_type' => 'column',
      'series' => [
        '#type' => 'chart_data',
        '#title' => $this->t('Forecasted Employment Growth Rate'),
        '#data' => $data,
      ],
      // 'series_annotation' => [
      //   '#type' => 'chart_data',
      //   '#title' => ['role' => 'annotation'],
      //   '#data' => array_map(function($v) {
      //     $options = array(
      //       'decimals' => 1,
      //       'suffix' => "%",
      //       'positive_sign' => TRUE,
      //     );
      //     return ssotFormatNumber($v, $options);
      //   }, $data),          
      // ],
      // 'series_tooltip' => [
      //   '#type' => 'chart_data',
      //   '#title' => ['type' => 'string', 'role' => 'tooltip', 'p' => ['html' => true]],
      //   '#data' => array_map(function($v, $d) {
      //     $tooltip = "Forecasted Employment Growth Rate: ";
      //     $options = array(
      //       'decimals' => 1,
      //       'suffix' => "%",
      //       'positive_sign' => TRUE,
      //     );
      //     // $tooltip = $d . " Forecasted Employment Growth Rate: ";
      //     // $tooltip .= ssotFormatNumber($v, $options);            
      //     $tooltip = "<b>" . $d . "<br></b> Forecasted Employment Growth Rate: ";
      //     $tooltip .= "<b>" . ssotFormatNumber($v, $options) . "</b>";
      //     return $tooltip;
      //   }, $data, $dates),          
      // ],        
      'xaxis' => [
        '#type' => 'chart_xaxis',
        '#labels' => $dates,
      ],
      'yaxis' => [
        '#type' => 'chart_yaxis',
        '#raw_options' => [
          'textPosition' => 'out',
          'gridlines' => [
            'count' => 1,
          ],
          'minValue' => 0,
        ]
      ],
      '#legend_position' => 'none',
    ];
    $content = \Drupal::service('renderer')->render($chart);


    return [
      '#markup' => $content,
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