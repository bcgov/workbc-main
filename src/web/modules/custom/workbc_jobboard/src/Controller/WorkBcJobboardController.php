<?php
namespace Drupal\workbc_jobboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\workbc_jobboard\Controller\apiServices;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Entity\Element\EntityAutocomplete;
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
      $apiServices = new apiServices();
      $data = $apiServices->fnGetPost($parameters, $action, $method);
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
      $apiServices = new apiServices();
      $results = $apiServices->fnGetPost([$cityname, 'true'], 'getCities', 'get');
      return new JsonResponse($results);
    }
  }
}