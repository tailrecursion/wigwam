<?php namespace Wigwam\HTTP\RequestBody;

class ByContentType {

  // Associative array mapping content_type => render_function
  private $handlers;

  public function __construct($config = array()) {
    $this->handlers = $config;
  }

  // Add a content_type handler
  public function addHandler($type, $handler) {
    $this->handlers[$type] = $handler;
  }

  // Parse the incoming request body parameters.
  public function parse($type, $body) {
    return array_key_exists($type, $this->handlers) 
      ? $this->handlers[$type]($body)
      : $body;
  }

}
