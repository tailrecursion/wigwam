<?php namespace Wigwam\Test;

use Wigwam\HTTP\Auth\Roles;

class App {

  public function getRoles() {
    return new Roles();
  }

  /**
   * @role deny
   */
  public static function getDoit() {
    return array('did' => 'it');
  }

}
