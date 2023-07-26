<?php
namespace Drupal\workbc_jobboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
 * {@inheritdoc}
 */
class WorkBcJobboardController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function getPosts($parameters='', $action="SearchPost", $method="post"){
    if(empty($parameters) && $method == 'post'){
      $response = ['response'=>401, 'message'=>'Unauthorized', 'data'=>[]];
    }else {
      $data = $this->api($parameters, $action, $method);
      if(!empty($data)){
        $response = ['response'=>200, 'message'=>'Success', 'data'=>$data];
      }else {
        $response = ['response'=>403, 'message'=>'No Data found', 'data'=>[]];
      }
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getCitiesJson(Request $request){
    $cityname = $request->query->get('q');
    $results = [];
    if(empty($cityname)){
      return new JsonResponse($results);
    }else {
      $results = $this->api([$cityname, 'true'], 'getCities', 'get');
      return new JsonResponse($results);
    }
  }

  /**
   * {@inheritdoc}
	 */
  function getCallOptions($parameters, $action, $method) {
    $options = [];
    if($action == 'SearchPost'){
      $jobboard_api_url = \Drupal::config('jobboard')->get('jobboard_api_url_backend').'/'.SEARCH_POST;
      $options['body'] = json_encode($parameters);
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
      if(isset($parameters['profile_id'])){
        $jobboard_api_url = \Drupal::config('jobboard')->get('jobboard_api_url_backend').'/'.SAVE_CAREER_PROFILE ."/".$parameters['profile_id'];
        unset($parameters['profile_id']);
        $options['headers'] = [];
        $options['headers'] = $parameters;
        $options['headers']['Accept'] = '*/*';
      }
    }
    else if ($action == 'statusProfile'){
      if(isset($parameters['profile_id'])){
        $jobboard_api_url = \Drupal::config('jobboard')->get('jobboard_api_url_backend').'/'.STATUS_CAREER_PROFILE ."/".$parameters['profile_id'];
        $options['headers'] = [];
        $options['headers']['Authorization'] = [$parameters['Authorization']];
        $options['headers']['Accept'] = '*/*';
        unset($parameters['Authorization']);
      }
    }
    else if ($action == 'saveIndustryProfile'){
      if(isset($parameters['profile_id'])){
        $jobboard_api_url = \Drupal::config('jobboard')->get('jobboard_api_url_backend').'/'.SAVE_INDUSTRY_PROFILE ."/".$parameters['profile_id'];
        unset($parameters['profile_id']);
        $options['headers'] = [];
        $options['headers'] = $parameters;
        $options['headers']['Accept'] = '*/*';
      }
    }
    else if ($action == 'statusIndustryProfile'){
      if(isset($parameters['profile_id'])){
        $jobboard_api_url = \Drupal::config('jobboard')->get('jobboard_api_url_backend').'/'.STATUS_INDUSTRY_PROFILE ."/".$parameters['profile_id'];
        $options['headers'] = [];
        $options['headers']['Authorization'] = [$parameters['Authorization']];
        $options['headers']['Accept'] = '*/*';
        unset($parameters['Authorization']);
      }
    }
    if($method == 'get'){
      if(is_array($parameters)){
        foreach($parameters as $para){
          $jobboard_api_url .= '/'.$para;
        }
      }else {
        $jobboard_api_url .= '/'.$parameters;
      }
    }
    $options['url'] = $jobboard_api_url;
    return $options;
  }

  /**
   * {@inheritdoc}
	 */
  function api($parameters, $action, $method) {
    try {
      $options = $this->getCallOptions($parameters, $action, $method);
      $client = new Client();
      $response = $client->$method($options['url'], $options);
      $result = json_decode($response->getBody(), TRUE);
      return $result;
    }
    catch (RequestException $e) {
      \Drupal::logger('workbc_jobboard')->error($e->getMessage());
      return NULL;
    }
  }
}
