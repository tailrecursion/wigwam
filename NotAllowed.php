<?php namespace Wigwam;

class NotAllowed extends Exception {

  public function __construct($message = "You are not allowed to do that.", $data = array()) {
    parent::__construct($message, $data);
    $this->setStatus(403);
  }

}
