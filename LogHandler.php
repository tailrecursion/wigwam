<?php namespace Wigwam;

class LogHandler {

  public function debug($message) {
    error_log($message);
  }

  public function info($message) {
    error_log($message);
  }

  public function warn($message) {
    error_log($message);
  }

  public function error($message) {
    error_log($message);
  }

  public function fatal($message) {
    error_log($message);
  }

}
