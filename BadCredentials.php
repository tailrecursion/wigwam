<?php namespace Wigwam;

class BadCredentials extends NotAllowed {

  public function __construct($message = "Incorrect username or password.", $data = array()) {
    parent::__construct($message, $data);
  }

}
