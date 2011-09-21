<?php namespace Wigwam;

class NotFound extends Exception {

  public function __construct($message = "Resource not found.", $data = array()) {
    parent::__construct($message, $data);
    $this->setStatus(404);
  }

}
