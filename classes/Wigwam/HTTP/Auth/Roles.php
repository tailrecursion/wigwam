<?php namespace Wigwam\HTTP\Auth;

/**
 * Base class containing logic for determining which roles the logged-in
 * user has.
 */
class Roles {

  /**
   * Method indicating whether the logged-in user has a particular role.
   * This is just a stub that always returns true. Overload this method
   * in your subclass to do the actual role resolution.
   *
   * @param   $r    string  The role (eg. "admin", "staff", etc.).
   * @param   $args array   Any arguments specified in the @role tag.
   * @return  boolean       TRUE if logged-in user has the role $r.
   */
  public function has($r, $args) {
    return true;
  }

}
