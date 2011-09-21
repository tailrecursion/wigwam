<?php namespace Wigwam;

class BadCredentials extends NotAllowed {

  public function __construct($message = "Username and password don't match.", $data = array()) {
    parent::__construct($message, $data);
  }

}
