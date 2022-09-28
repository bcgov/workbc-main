<?php
namespace Drupal\workbc_jobboard\Controller;

use Drupal\Core\Controller\ControllerBase;
require_once(DRUPAL_ROOT . '/modules/custom/workbc_jobboard/config/common.inc');

/**
	*{@inheritdoc}
	*/
class apiServices extends ControllerBase{
  
  /**
	*{@inheritdoc}
	*/	
  function fnGetService($service, $action, $parameters){
    if(is_file(DRUPAL_ROOT . '/modules/custom/workbc_jobboard/config/config.ini')){  
      $host = (!isset($_COOKIE['Drupal_visitor_environment']) && !defined('Drupal_visitor_environment')) ? fnGetEnvironment() : ((isset($_COOKIE['Drupal_visitor_environment'])) ? $_COOKIE['Drupal_visitor_environment'] : Drupal_visitor_environment);
      
      $config = parse_ini_file(DRUPAL_ROOT . '/modules/custom/workbc_jobboard/config/config.ini', true);
      if(isset($action) && !empty($action)) {
				if( isset($parameters) ) {
					return (isset($config[$service])) ? $config[$service][$host] .'/'. $config['SERVICES'][$action] . '/' .$parameters : $service .'/'. $config['SERVICES'][$action];
				} else {
					
				  return (isset($config[$service])) ? $config[$service][$host] .'/'. $config['SERVICES'][$action] : $service .'/'. $config['SERVICES'][$action];
				}
			}
			else {
				return $config[$service][$host];
			}
    }
    else{
			return false;
		}
  }
  
  /**
	*{@inheritdoc}
	*/	
  function fnGetCurlRequest($action, $parameters = null, $get_vars = false, $cid = NULL, $cacheExpire = 7200, $cookie_vals = '', $ret_cookies = false, $trace = false, $header = array()){
    $host = (!isset($_COOKIE['Drupal_visitor_environment']) && !defined('Drupal_visitor_environment')) ? fnGetEnvironment() : ((isset($_COOKIE['Drupal_visitor_environment'])) ? $_COOKIE['Drupal_visitor_environment'] : Drupal_visitor_environment);
    
    $url = $this->fnGetService('WS-HOSTS', $action, $parameters);
    $r = array();
		if(!empty($cookie_vals) && is_array($cookie_vals)){
		  $cookie = implode('&', $cookie_vals);
		}
		else
		{
		  if(!empty($cookie_vals)){
			$cookie = $cookie_vals;
		  }
		  else
		  {
			$cookie = NULL;
		  }
		}
    
    if($url){
		  // create a new cURL resource
		  $ch = curl_init();
      
		  // TODO: The services are hosted by xxx and currently need authentication.
		  $username = '';
		  $password = '';
      if(empty($username) && empty($password)){
        $url = str_replace("user:pass@","", $url);
      }else {
        $url = str_replace(["user",'pass'],[$username, $password], $url);
      }
      // set URL and other appropriate options
		  curl_setopt($ch, CURLOPT_URL, $url);
		  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,0);
		  curl_setopt($ch, CURLOPT_TIMEOUT, 800); //timeout in seconds

		  if (!empty($header)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		  }
		  else {
        curl_setopt($ch, CURLOPT_HEADER, "Content-Type:application/xml");
		  }

		  if($trace == true){
        curl_setopt($ch, CURLOPT_STDERR, fopen('php://output', 'w'));
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
		  }
		  else{
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      }

		  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

		  if($trace == true){
        curl_setopt($ch, CURLOPT_HEADER, 1);
      }
		  else{
        curl_setopt($ch, CURLOPT_HEADER, 0);
      }

		  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

		  if(!empty($cookie)){
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_COOKIESESSION, 1);
		  }

		  //try & catch for curl request to get url
		  try {
        // grab URL and pass it to the browser
        $ret = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if($httpcode == 200 ){
          if($get_vars == true){
            $info = curl_getinfo($ch);
            if(isset($info['url'])){
              $url = parse_url($info['url']);

              parse_str($url['query'], $r);

              $r['query_string'] = $r;
            }
          }

          if($ret_cookies == true){
            // get cookie
            preg_match('/^Set-Cookie:\s*([^;]*)/mi', $ret, $m);
            $r['cookies'] = $m;
          }
          $r['response'] = $ret;
          
          return $r;
        }
        return false;
		  }
		  catch (Exception $e) {
        return false;
		  }
		} else{
		  //$r = fnGetCache($cid);
		}
		return $r;
  }
  
  /**
	*{@inheritdoc}
	*/	
  function fnGetRecentPost($parameters='') {
    return $this->fnGetCurlRequest('GET_RECENT_POST', $parameters , false);
  }
}