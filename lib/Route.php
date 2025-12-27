<?php

/**
 *  Route class inspired by laravel routing
 *  Nikola Stamatovic Sept. 2015
 *
 *  profile/{var_name} wildcard -> token or id
 */
class Route {
  public static $path = '/';
  public static $path_segments = array();
  public static $path_count = 0;
  public static $method = '';
  public static $routed = false;
  public static $data = array();

  private function __construct() {}
  public static function __init__() {
    //$path = @$_SERVER['PATH_INFO'];
    $path = @$_SERVER['REQUEST_URI'];
    $pref = @$_SERVER['SCRIPT_NAME'];


    if ($pref && preg_match('/^(.*)\/[^\/]+\.php$/', $pref, $m)) {
        if (preg_match('/^(.*)\/[^\/]+\.php/', $path)) {
            $path = str_replace($m[0],'',$path);
        } else {
            $path = str_replace($m[1],'',$path);
        }
    }

    self::$path = !empty($path) ? strtok($path, "?") : '/' ;

    if (!empty(self::$path) && self::$path !== "/") {
      self::$path_segments = explode( "/",  substr(self::$path, 1));
      self::$path_count = count(self::$path_segments);
    }
    self::$method = $_SERVER['REQUEST_METHOD'];

    switch (self::$method) {
      case 'GET':
        self::$data = $_GET;
        break;
      case 'POST':
        self::$data = $_POST;
        break;
      default:
        parse_str(file_get_contents("php://input"), self::$data);
    }
  }

public static function follow($route, $params=null) {
    //global $db, $username, $user_id, $hostname, $loggedin; //TODO: Solve this ugliness :)

    if (preg_match('/([a-zA-Z0-9_]+)@([a-zA-Z_][a-zA-Z_0-9]*)/', $route, $m)) { //interface method (controller)
      if(method_exists($m[1], $m[2])) {
        $rq = array_merge($params, self::$data);
        return call_user_func(array($m[1], $m[2]), $rq);
      } else {
        trigger_error("Unable to call static method: $m[1]::$m[2]", E_USER_WARNING);
      }
    } else { // page(view)
      //add a pagename to params if it doesnt exist
      if (empty($params)) $params = array();
      if (!isset($params['page']) && preg_match('/\/?([^\.\/]+)$/', $route, $m)) {
        $params['page'] = $m[1];
      }

      //extract($params, EXTR_SKIP);

      foreach ($GLOBALS as $key => $val) { global $$key; } //SAFEINCLUDE
      foreach ($params as $key => $val) { $GLOBALS[$key] = $val; global $$key; }

      include($route.'.php');
      self::$routed = true;
      exit();
    }
  }

  public static function _($method, $path, $route, $params=null) {
    if (self::$method !== $method) return;
    if (empty($path) || empty($route)) return;

    $rpath = self::$path;

    if (!preg_match('/^\//', $path)) {
      $path = '/'.$path;
    }

    //treat wildcard vars
    if (preg_match('/\{[0-9A-Za-z_]*\}/', $path)) {
      $pt = explode('/', substr($path, 1));

      if (count($pt) !== self::$path_count) {
        return;
      }

      $ids = array();
      $varnames = array();

      foreach ($pt as $k => $v) {
        if( preg_match('/^\{([a-zA-Z_][a-zA-Z_0-9]*)\}$/', $v, $m)) {
          array_push($ids, $k);
          array_push($varnames, $m[1]);
        }
      }

      if (empty($params)) $params = array();
      $rpt = explode('/', substr($rpath, 1));

      foreach ($ids as $k => $v) {
          $params[$varnames[$k]] = $rpt[$v];
          $rpt[$v] = '{'.$varnames[$k].'}';
      }

      $rpath = '/'.implode('/', $rpt);
    }

    if ($path !== $rpath) return;
    if (is_callable($route)) return $route($params);
    return self::follow($route, $params);
  }

  public static function get($path, $route, $params=null) {
    return self::_('GET', $path, $route, $params);
  }

  public static function put($path, $route, $params=null) {
    return self::_('PUT', $path, $route, $params);
  }

  public static function post($path, $route, $params=null) {
    return self::_('POST', $path, $route, $params);
  }

  public static function delete($path, $route, $params=null) {
    return self::_('DELETE', $path, $route, $params);
  }

  public static function fail($p404) {
    if (!self::$routed) {
      header('HTTP/1.0 404 Not Found');
      include $p404;
      exit();
    }
  }

  public static function segment_count() {
    return self::$path_count;
  }

  public static function get_segment($id) {
    return self::$path_segments[$id];
  }
}

Route::__init__();
