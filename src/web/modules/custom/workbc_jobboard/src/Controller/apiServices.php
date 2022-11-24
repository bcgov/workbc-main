<?php
namespace Drupal\workbc_jobboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

const SEARCH_POST = 'api/Search/JobSearch';

const GETTOTAL_JOBS = 'api/Search/gettotaljobs';

const GET_CITIES = 'api/location/cities';


/**
 *{@inheritdoc}
 */
class apiServices extends ControllerBase{

  /**
   *{@inheritdoc}
	 */
  function __construct() {

  }

  /**
   *{@inheritdoc}
	 */
  function fnGetPost($parameter='', $action="", $method="") {
    $options = [];
    $options['read_timeout'] = null;
    if($action == 'SearchPost'){
      $jobboard_api_url = \Drupal::config('jobboard')->get('jobboard_api_url_backend').'/'.SEARCH_POST;
      $options['body'] = json_encode($parameter);
      $options['headers'] = [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json'
      ];
    }
    else if ($action == 'getTotalJobs'){
      $jobboard_api_url = \Drupal::config('jobboard')->get('jobboard_api_url_backend').'/'.GETTOTAL_JOBS;
    }
    else if ($action == 'getCities'){
      $jobboard_api_url = \Drupal::config('jobboard')->get('jobboard_api_url_backend').'/'.GET_CITIES;
    }
    if($method == 'get'){
      if(is_array($parameter)){
        foreach($parameter as $para){
          $jobboard_api_url .= '/'.$para;
        }
      }else {
        $jobboard_api_url .= '/'.$parameter;
      }
    }
    try {
      $client = new Client();
      $response = $client->$method($jobboard_api_url, $options);
      $result = json_decode($response->getBody(), TRUE);
      return $result;
    }
    catch (RequestException $e) {
      \Drupal::logger('workbc_jobboard')->error($e->getMessage());
      return NULL;
    }
  }
}