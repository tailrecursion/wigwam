<?php namespace Wigwam;

use Slim;
use Wigwam\Reflect;
use Wigwam\HTTP\Auth;
use Wigwam\HTTP\View\ByAcceptHeader;
use Wigwam\HTTP\RequestBody\ByContentType;
use Wigwam\HTTP\Session\Noop;
use Wigwam\Utils\ArrayUtils;

class HTTP {

  public  $err;
  private $requestBodyParser;
  private $apps;
  private $classesDir;

  //===========================================================================//
  // SETUP SLIM & HTTP WRAPPER                                                 //
  //===========================================================================//

  public function __construct($config=array()) {

    $this->classesDir = dirname(__FILE__)."/..";

    require $this->classesDir.'/Wigwam/vendor/Slim/Slim.php';

    $xthis      = $this;
    $this->apps = array();

    // Load config from YAML if $config is an array of filenames (we know this
    // when $config is not an associative array of key/value pairs.

    if (! ArrayUtils::isAssoc($config)) {
      array_unshift($config, $this->classesDir.'/Wigwam/config/config.yaml');
      $config = HTTP::yamlConfig($config);
    }

    // Set error reporting.

    if (isset($config['error_reporting']))
      error_reporting($config['error_reporting']);

    // Non-fatal, non-parse, etc. errors.

    $error_handler = function($errno, $errstr, $errfile, $errline) {
      error_log("Wigwam error: $errstr in $errfile on line $errline");
    };
    set_error_handler($error_handler);

    // Fatal errors.

    $shutdown_handler = function() {
      $error    = error_get_last();
      $pattern  = "/^Uncaught exception 'Slim_Exception_Stop'/";
      $template = "Wigwam fatal error %d: %s in %s on line %d";

      if (!$error['type'] || preg_match($pattern, $error['message']))
        return;

      error_log(sprintf(
        $template,
        $error['type'],
        $error['message'],
        $error['file'],
        $error['line']
      ));
    };
    register_shutdown_function($shutdown_handler);

    // Create the request body parser object, and populate it
    // with content-type mappings. Right now there is only the
    // one for content-type 'application/json', but we could
    // have others, such as XML, HTML, plain text, etc.

    $this->requestBodyParser = new ByContentType(array(
      'application/php' => function ($data) {
        return unserialize($data);
      },
      'application/json' => function ($data) {
        return json_decode($data, true);
      },
    ));

    // Configure the three Slim application modes.

    Slim::configureMode('production', function () {
      Slim::config(array(
        'debug'       => false,
        'log.enable'  => true,
        'log.level'   => 2,
      ));
    });

    Slim::configureMode('staging', function () {
      Slim::config(array(
        'debug'       => false,
        'log.enable'  => true,
        'log.level'   => 4,
      ));
    });

    Slim::configureMode('development', function () {
      Slim::config(array(
        'debug'       => false,
        'log.enable'  => true,
        'log.level'   => 4,
      ));
    });

    // Create the view renderer object, and populate it with content-type 
    // mappings. Right now there are only the ones for content-type 
    // 'application/json' and 'text/javascript', but we could have others, 
    // such as XML, HTML, plain text, etc.

    $theView = new ByAcceptHeader(array(
      'application/php' => function ($data) {
        return serialize($data);
      },

      'application/json' => function ($data) {
        return str_replace('\\/', '/', json_encode($data));
      },

      // Returns javascript that assigns the data to a variable, for example:
      //
      // PHP response data:
      //
      //    array('foo' => 'bar');
      // 
      // Request URI: 
      //
      //    /some/route/?var=baz
      //
      // Response text:
      //
      //    window.baz = $.extexd(window.baz === undefined ? {} : window.baz, { "foo" : "bar" });
      //
      // The default variable name is simply 'var' (i.e, window.var).

      'text/javascript' => function ($data) use ($xthis) {
        $var = is_null($xthis->request()->get('var')) 
          ? 'data' : $xthis->request()->get('var');
        return "window.$var = \$.extend(window.$var === undefined ? {} : window.$var, "
          .json_encode($data).');';
      },

      // Default to text/javascript because then you can use this to load
      // data via a <script> tag, which does not let you set any accepts
      // headers and always sends 'Accepts: */*'.

      '*/*' => 'text/javascript',
    ));

    // Initialize the Slim application framework.

    $sessionHlr = $config['http.session'];

    Slim::init(array(
      'templates.path'    => $this->classesDir.'/Wigwam/vendor/templates',
      'view'              => $theView,
      'mode'              => $config['http.mode'],
      'log.path'          => $config['http.log'],
      'session.handler'   => $sessionHlr ? $sessionHlr : new Noop(),
    ));

    // HTTP error handler.

    Slim::error(function() use ($xthis) {

      // The exception.

      $err = $xthis->err;

      // Populate the error response object.

      if (is_a($err, '\Exception')) {
        $eName    = (string) get_class($err);
        $message  = $err->getMessage();
      } else {
        $eName    = "";
        $message  = (string) $err;
      }

      $status = 500;

      $data   = array(
        'exception' => $eName,
        'message'   => $message,
      );

      // Merge in the data if the exception is a Wigwam\Exception subclass, and
      // if there is any data to merge.

      if (is_a($err, 'Exception')) {
        $status = $err->getStatus();
        $data = ArrayUtils::merge_recursive_overwrite($data, $err->getData());
      }

      // Get the response body for the error data object. The render method
      // sets the content-type for the response and returns the response body
      // text. This may fail, however, so at the very least return no body.

      try {
        Slim::config('view')->setData('data', $data);
        $body = Slim::config('view')->render('dummy');
      } catch (Exception $e) {
        $body = NULL;
      }

      // Stop execution and send HTTP response immediately.

      Slim::halt($status, $body);
    });

    // HTTP 404 handler. If a route is requested but not defined below then
    // this function is called.

    Slim::notFound(function() use ($xthis) {
      $xthis->error(new NotFound());
    });

    // Only accept secure requests. An insecure request has compromised
    // the session cookie, so we destroy the session data, session cookie,
    // and send an error response.

    // if (! array_key_exists('HTTPS', $_SERVER)) {
    //   HTTP::destroy_session();
    //   $xthis->error(new NotAllowed("SSL required."));
    // }

    // List of 'safe' HTTP methods. Used to determine correct behavior
    // for caching, anti-CSRF, etc.

    $safe   = array('GET', 'HEAD', 'OPTIONS');
    $method = Slim::request()->getMethod();

    // Immediately return an error if unsafe request and the CSRF
    // synchronization token is not provided or not correct. A valid token
    // is then returned in the response.

    // Allow for use of magic 'csrftoken' hidden field in html forms.

    $test_token = Slim::request()->post('csrftoken');

    if (!$test_token)
      $test_token = Slim::request()->headers('X-CSRFToken');

    $true_token = md5(session_id());

    if (!in_array($method, $safe) && $test_token != $true_token) {
      $this->error(new BadCSRFToken(
        "Bad CSRF token.",
        array('token'=>$true_token)
      ));
    }

  }

  // Parse some YAML files, overwriting values with values in later files
  // when keys overlap.

  public static function yamlConfig($config_files) {
    return array_reduce(
      $config_files,
      function($acc, $e) {
        return is_readable($e)
          ? ArrayUtils::merge_recursive_overwrite($acc, yaml_parse_file($e))
          : $acc;
      },
      array()
    );
  }

  // Create routes for applicable public static methods of $app.

  public function addApi($app) {
    $xthis    = $this;
    $rfl      = new Reflect($app);
    $rfl_path = preg_replace('/\\\\/','/',$rfl->getName());

    $rfl->addParseHandler(function($method) use ($xthis, $rfl, $rfl_path) {
      $method['verb']   = preg_replace('/[[:upper:]].*$/', '', $method['name']);
      $method['route']  = '/'.$rfl_path.'/'.$method['name'];

      if (in_array($method['verb'], array('get','post','put','delete')))
        call_user_func_array(
          array($xthis, $method['verb']),
          array(
            $method['route'],
            function() use ($xthis, $rfl, $method) {
              $xthis->render($rfl->apply($method['name'], $xthis->params()));
            }
          )
        );

      return $method;
    });

    $rfl->addTagHandler('role', new Auth($app));
    $this->apps[] = $rfl->run();
  }

  // Unset session data and destroy session cookie.

  public function destroy_session() {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
  }

  public function render($data) {
    return Slim::render('dummy', array('data' => $data));
  }

  public function params() {
    $ret = Slim::request()->get();
    $ret = ArrayUtils::merge_recursive_overwrite($ret, Slim::request()->post());
    return ArrayUtils::merge_recursive_overwrite($ret, $this->parseBody());
  }

  public function parseBody() {
    return $this->requestBodyParser->parse(
      Slim::request()->getContentType(),
      Slim::request()->getBody()
    );
  }

  public function config() {
    return call_user_func_array('Slim::config', func_get_args());
  }

  public function request() {
    return call_user_func_array('Slim::request', func_get_args());
  }

  public function etag() {
    return call_user_func_array('Slim::etag', func_get_args());
  }

  public function lastModified() {
    return call_user_func_array('Slim::lastModified', func_get_args());
  }

  public function get() {
    return call_user_func_array('Slim::get', func_get_args());
  }

  public function post() {
    return call_user_func_array('Slim::post', func_get_args());
  }

  public function put() {
    return call_user_func_array('Slim::put', func_get_args());
  }

  public function delete() {
    return call_user_func_array('Slim::delete', func_get_args());
  }

  public function error($e) {
    $this->err = $e;
    return Slim::error();
  }

  public function routes($routes) {
    foreach ($routes as $route => $actions) {
      foreach ($actions as $method => $action) {
        $method = strtolower($method);
        call_user_func('Slim::'.$method, $route, $action);
      }
    }
  }

  public function makeJSRuntime($api) {
    Slim::response()->header('Content-Type', 'text/javascript');
    include($this->classesDir.'/Wigwam/jsruntime.php');
  }

  public function run($routes=null) {
    $xthis  = $this;
    $apps   = $this->apps;

    $this->get('/api', function() use ($xthis, $apps) {
      $xthis->render($apps);
    });

    $this->get('/wigwam.js', function() use ($xthis, $apps) {
      $xthis->makeJSRuntime($apps);
    });

    try {
      if (! is_null($routes))
        $this->routes($routes);
      Slim::run();
    } catch (Exception $e) {
      $this->error($e);
    }
  }

}
