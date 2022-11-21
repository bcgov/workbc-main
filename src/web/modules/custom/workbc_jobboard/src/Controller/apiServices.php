<?php
namespace Drupal\workbc_jobboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

const SEARCH_POST = 'api/Search/JobSearch';

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
  function fnGetPost($parameter='', $action="SearchPost", $method="post") {
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