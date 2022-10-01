<?php
namespace Drupal\workbc_jobboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

const GET_RECENT_POST = 'api/career-profiles/topjobs';
  
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
  function fnGetRecentPost($parameter='', $read_timeout=null) {
    $api_url = \Drupal::config('jobboard')->get('api_url').'/'.GET_RECENT_POST;
    if(!empty($parameter)){
      $api_url .= "/".$parameter;
    }
    $client = new Client();
    try {
      $options = [];
      if ($read_timeout) {
        $options['read_timeout'] = $read_timeout;
      }
      $response = $client->get($api_url, $options);
      $result = json_decode($response->getBody(), TRUE);
      return $result;
    }
    catch (RequestException $e) {
      \Drupal::logger('workbc_jobboard')->error($e->getMessage());
      return NULL;
    }
  }
}