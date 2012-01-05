<?php namespace Wigwam\HTTP;

class Logger extends \Wigwam\LogHandler {

  public function debug($message) {
    \Slim_Log::debug($message);
  }

  public function info($message) {
    \Slim_Log::info($message);
  }

  public function warn($message) {
    \Slim_Log::warn($message);
  }

  public function error($message) {
    \Slim_Log::error($message);
  }

  public function fatal($message) {
    \Slim_Log::fatal($message);
  }

}
