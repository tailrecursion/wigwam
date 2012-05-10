<?php namespace Wigwam\HTTP;

use \Wigwam\NotAllowed;

class Verb {

  private $app;

  public function __construct($app) {
    $this->app = $app;
  }

  function run($method, $verb) {
    //error_log("verb comin up...");
    //error_log(var_export($verb, true));
  }

}
