<?php namespace Wigwam;

class FatalException extends Exception {

  public function __construct($message = "A fatal error occurred.", $data = array()) {
    parent::__construct($message, $data);
  }

}
