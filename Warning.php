<?php namespace Wigwam;

class Warning extends Exception {

  protected static $severity = "warning";

  public function __construct($message, $data = array()) {
    parent::__construct($message, $data);
    $this->setStatus(500);
  }

}
