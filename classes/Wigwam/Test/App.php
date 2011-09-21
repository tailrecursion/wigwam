<?php namespace Wigwam\Test;

use Wigwam\HTTP\Auth\Roles;

class App {

  /**
   * This implements the App interface (unofficial). Used by the HTTP wrapper
   * for roles based auth. You'd normally subclass the Roles class to implement
   * your own roles based auth system.
   *
   * @return object The roles subclass object.
   */
  public function getRoles() {
    return new Roles();
  }

  /**
   * Public static method: this method will be part of the public API, access-
   * ible via the HTTP interface. You'd call this method with a GET request to
   * /Wigwam/Test/App/getDoit. The response should be an object with the pro-
   * perty 'did' set to the value 'bar', in the format specified by the request
   * accepts header (i.e., Accepts: application/json -> { "did" : "it" }).
   *
   * Using the wigwam base roles class, so any role (including deny) will pass.
   * Normally you'd subclass it to have a functioning roles-based authorization
   * and authentication system.
   *
   * @role deny
   */
  public static function getDoit() {
    return array('did' => 'it');
  }

}
