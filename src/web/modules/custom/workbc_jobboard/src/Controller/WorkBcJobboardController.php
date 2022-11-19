<?php
namespace Drupal\workbc_jobboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\workbc_jobboard\Controller\apiServices;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * {@inheritdoc}
 */
class WorkBcJobboardController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function getPosts($parameters=0){
    if(empty($parameters)){
      $response = ['response'=>401, 'message'=>'Unauthorized', 'data'=>[]];
    }else {
      $apiServices = new apiServices();
      $data = $apiServices->fnGetPost($parameters);
      if(!empty($data)){
        $response = ['response'=>200, 'message'=>'Success', 'data'=>$data];
      }else {
        $response = ['response'=>403, 'message'=>'No Data found', 'data'=>[]];
      }
    }
    return $response;
  }
}