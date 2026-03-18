<?php
namespace Drupal\calendar_listview\Controller;

use Drupal\Core\Controller\ControllerBase;


/**
 * Class CalendarListviewController
 * @package Drupal\calendar_listview\Controller
 */

class CalendarListviewController extends ControllerBase{
  
  /**
  * 
  */
  protected $view;
  
  /**
  * 
  */
  public function __construct(){
    
  }
  
  /**
  * 
  */
  public function viewmode($viewmode='calendar'){
    $renderable = [
      '#theme' => 'calendarviewmode',
      '#viewmode' => $viewmode,
    ];
    $rendered = \Drupal::service('renderer')->render($renderable);
    $output['markup'] = [
      '#markup' => '<div id="calendar_viewmode '.$viewmode.'">'.$rendered.'</div>',
    ];
    return $output;
  }
}