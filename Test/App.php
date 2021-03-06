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
   *
   * @return array What it did.
   */
  public static function getDoit() {
    return array('did' => 'it');
  }

  /**
   * This method will throw an exception that can be caught in JS.
   *
   * @param foo number The foo.
   * @param bar number The bar.
   * @return number The sum of the foo and the bar.
   * @throws \Wigwam\NotAllowed
   */
  public static function getOops($foo, $bar) {
    if ($foo > 100)
      throw new \Wigwam\NotAllowed("too much foo", array('bar' => 'baz'));
    return $foo + $bar;
  }
}
