<?php namespace Wigwam;

class BadArgument extends Exception {

  public function __construct($message = "Bad or missing argument.", $data = array()) {
    parent::__construct($message, $data);
  }

}
