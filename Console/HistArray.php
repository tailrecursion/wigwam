<?php namespace Wigwam\Console;

use ArrayAccess;

class HistArray implements ArrayAccess {

  public $coll = array();

  private function i($i) {
    return $i + ($i < 0 ? count($this->coll) : 0);
  }

  public function offsetExists($i) {
    return isset($this->coll[$this->i($i)]);
  }

  public function offsetGet($i) {
    $i = $this->i($i);
    return isset($this->coll[$i]) ? $this->coll[$i] : null;
  }

  public function offsetSet($i, $v) {
    if (is_null($i))
      $this->coll[] = $v;
    else
      $this->coll[$this->i($i)] = $v;
  }

  public function offsetUnset($i) {
    unset($this->coll[$this->i($i)]);
  }

  public function getArrayCopy() {
    $ret = $this->coll;
    return $ret;
  }
}


