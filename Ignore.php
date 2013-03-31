<?php namespace Wigwam;

class Ignore extends Exception {

  protected static $severity = "ignore";

  public function __construct($message="Ignore this exception.", $data=array()) {
    parent::__construct($message, $data);
    $this->setStatus(500);
  }

}
