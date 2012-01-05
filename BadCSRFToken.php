<?php namespace Wigwam;

class BadCSRFToken extends NotAllowed {

  public function __construct($message = "Bad CSRF token.", $data = array()) {
    parent::__construct($message, $data);
  }

}
