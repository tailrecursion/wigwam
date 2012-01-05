<?php namespace Wigwam\HTTP;

use \Wigwam\NotAllowed;

class Auth {

  private $app;
  private $ctx;

  public function __construct($app) {
    $this->app = $app;
  }

  function run($method, $rolesList) {
    $this->ctx = $this->ctx ? $this->ctx : $this->app->getRoles();

    if (!$this->assertRoles($rolesList))
      throw new NotAllowed();
  }

  function assertRoles($rolesList) {
    $ctx = $this->ctx;
    
    return count(array_filter(array_map(function($line) use ($ctx) {
      return array_reduce($line, function($acc, $spec) use ($ctx) {
        return $acc && $ctx->has($spec['name'], $spec['args']);
      }, true);
    }, $rolesList))) > 0;
  }

}
