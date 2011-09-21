<?php namespace Wigwam;

class NotAcceptable extends Exception {

  public function __construct($message = "No acceptable content type available.", $data = array()) {
    parent::__construct($message, $data);
    $this->setStatus(406);
  }

}
