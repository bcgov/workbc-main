<?php
namespace Drupal\workbc_jobboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

const SEARCH_POST = 'api/Search/JobSearch';

const GETTOTAL_JOBS = 'api/Search/gettotaljobs';

const GET_CITIES = 'api/location/cities';

const SAVE_CAREER_PROFILE = 'api/career-profiles/save';

const STATUS_CAREER_PROFILE = 'api/career-profiles/status';

const SAVE_INDUSTRY_PROFILE = 'api/industry-profiles/save';

const STATUS_INDUSTRY_PROFILE = 'api/industry-profiles/status';


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
    else if ($action == 'saveProfile'){
      if(isset($parameter['profile_id'])){
        $jobboard_api_url = \Drupal::config('jobboard')->get('jobboard_api_url_frontend').'/'.SAVE_CAREER_PROFILE ."/".$parameter['profile_id'];
        unset($parameter['profile_id']);
        $options['headers'] = [];
        $options['headers'] = $parameter;
        $options['headers']['Accept'] = '*/*';
      }
    }
    else if ($action == 'statusProfile'){
      if(isset($parameter['profile_id'])){
        $jobboard_api_url = \Drupal::config('jobboard')->get('jobboard_api_url_frontend').'/'.STATUS_CAREER_PROFILE ."/".$parameter['profile_id'];
        $options['headers'] = [];
        $options['headers']['Authorization'] = [$parameter['Authorization']];
        $options['headers']['Accept'] = '*/*';
        unset($parameter['Authorization']);
      }
    }
    else if ($action == 'saveIndustryProfile'){
      if(isset($parameter['profile_id'])){
        $jobboard_api_url = \Drupal::config('jobboard')->get('jobboard_api_url_frontend').'/'.SAVE_INDUSTRY_PROFILE ."/".$parameter['profile_id'];
        unset($parameter['profile_id']);
        $options['headers'] = [];
        $options['headers'] = $parameter;
        $options['headers']['Accept'] = '*/*';
      }
    }
    else if ($action == 'statusIndustryProfile'){
      if(isset($parameter['profile_id'])){
        $jobboard_api_url = \Drupal::config('jobboard')->get('jobboard_api_url_frontend').'/'.STATUS_INDUSTRY_PROFILE ."/".$parameter['profile_id'];
        $options['headers'] = [];
        $options['headers']['Authorization'] = [$parameter['Authorization']];
        $options['headers']['Accept'] = '*/*';
        unset($parameter['Authorization']);
      }
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