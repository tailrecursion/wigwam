<?php namespace Wigwam;

class Notice extends Exception {

  protected static $severity = "notice";

  public function __construct($message, $data = array()) {
    parent::__construct($message, $data);
    $this->setStatus(500);
  }

}
