<?php namespace Wigwam;

class Exception extends \Exception {

  private $status;
  private $data;

  public function __construct($message = NULL, $data = array()) {
    if (!is_null($message)) {
      parent::__construct($message);
    }
    $this->setData($data);
    $this->setStatus(500);
  }

  public function getStatus() {
    return $this->status;
  }

  public function setStatus($status) {
    $this->status = $status;
  }

  public function getData() {
    return $this->data;
  }

  public function setData($data) {
    $this->data = $data;
  }

}
