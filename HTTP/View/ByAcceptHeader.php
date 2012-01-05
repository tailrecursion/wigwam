<?php namespace Wigwam\HTTP\View;

use \Wigwam\NotAcceptable;
use \Slim;
use \Slim_View;

// This class renders content based on the Accept headers
// the client provides with the request.
// -- Micha

class ByAcceptHeader extends Slim_View {

  // Associative array mapping content_type => render_function
  private $handlers;

  public function __construct($config = array()) {
    $this->handlers = $config;
  }

  // Add a content_type handler
  public function addHandler($type, $handler) {
    $this->handlers[$type] = $handler;
  }

  // Convert to string in proper format.
  public function doConversion($data) {

    // Recursively convert Model objects into arrays
    $to_array = function($a) use (&$to_array) {
      if (is_array($a)) {
        $ret = array();
        foreach ($a as $key => $val) {
          $ret[$key] = $to_array($val);
        }
        return $ret;
      } elseif (is_object($a) && method_exists($a, 'toArray')) {
        return $to_array($a->toArray());
      } else {
        return $a;
      }
    };

    $data = $to_array($data);

    // Pick out the content-types requested in the Accept
    // headers.
    $accept = array_map(
      function($v) {
        return preg_replace("/;.*/", "", $v);
      },
      preg_split("/, */", Slim::request()->headers('ACCEPT'))
    );

    // Match the registered content-type handler to the type(s) requested in 
    // the Accept headers. One level of abstraction is supported, i.e. a key
    // can point to another key, but the second key must point to a function.
    foreach ($accept as $type) {
      if (array_key_exists($type, $this->handlers)) {
        $type = is_string($this->handlers[$type]) 
          ? $this->handlers[$type] : $type;
        if (array_key_exists($type, $this->handlers)) {
          Slim::response()->header('Content-Type', $type);
          return $this->handlers[$type]($data);
        }
      }
    }

    // Default is NotAcceptable exception
    throw new NotAcceptable();
  }

  // Implementing the Slim_View interface
  public function render($template) {
    // Every response includes the anti-csrf token in the headers.
    Slim::response()->header('X-CSRFToken', md5(session_id()));

    // Return the response text in an acceptable content-type.
    return $this->doConversion($this->data['data']);
  }

}

