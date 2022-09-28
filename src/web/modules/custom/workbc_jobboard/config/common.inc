<?php
  $protocol = explode('/', $_SERVER['SERVER_PROTOCOL']);

  define('HOST', ((isset($_SERVER['HTTP_X_FORWARDED_HOST'])) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['SERVER_NAME']));
  define('BASE', $_SERVER['SERVER_NAME']);
  define('PROTOCOL', strtolower($protocol[0]));
  
  function fnGetEnvironment() {
    $config = parse_ini_file(DRUPAL_ROOT . '/modules/custom/workbc_jobboard/config/config.ini', true);

		$env = 'LOCALHOST';

		if (in_array(HOST, $config['ENVIRONMENTS'])) {
			foreach($config['ENVIRONMENTS'] as $key => $value) {
				if ($value == HOST) {
					$env = $key;
					break;
				}
			}
		}
		return $env;
  }
  
?>