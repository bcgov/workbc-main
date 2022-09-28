<?php
namespace Drupal\workbc_jobboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\workbc_jobboard\Controller\apiServices;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * {@inheritdoc}
 */
class WorkBcJobboardController extends ControllerBase
{	
  /**
   * {@inheritdoc}
   */
	public function getRecentPosts($parameters=0){
    if(empty($parameters)){
      $response = ['response'=>401, 'message'=>'Unauthorized', 'data'=>[]];
    }else {
      $apiServices = new apiServices();
      $data = $apiServices->fnGetRecentPost($parameters);
      if(!empty($data)){
        $response = ['response'=>200, 'message'=>'Success', 'data'=>$data['response']];
      }else {
        $response = ['response'=>403, 'message'=>'No Data found', 'data'=>[]];
      }
    }
    return $response;
  }
  
  /**
   * {@inheritdoc}
   */
  /*public function getRecentJobsJson($noccode=0){
    if(!empty($noccode)){
      $data = file_get_contents(drupal_get_path('module', 'workbc_jobboard').'/storage/recent_jobs.json');
       print $data;
       die;
    }
  } */
}