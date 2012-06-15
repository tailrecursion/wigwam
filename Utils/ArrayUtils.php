<?php namespace Wigwam\Utils;

class ArrayUtils {

  /**
   * Performs a topological sort on a directed acyclic graph.
   *
   * @param $dag array The DAG.
   * @return array The sorted nodes.
   */
  public static function tsort($dag) {
    $l = array();
    $s = function(&$dag) {
      foreach ($dag as $k => $v)
        if (! count($v)) {
          unset($dag[$k]);
          return $k;
        }
      return null;
    };
    while ($n = $s($dag)) {
      array_unshift($l, $n);
      foreach ($dag as $k => &$v)
        if (false !== ($i = array_search($n, $v)))
          array_splice($v, $i, 1);
    }
    return $l;
  }

 /**
  * Merge two arrays recursively, overwriting original keys.
  * 
  * @param array   $array1: The original array.
  * @param array   $array2: The array to merge with.
  * @return array  The merged array.
  */

  public static function merge_recursive_overwrite($array1, $array2){
    if (!is_array($array1) || !is_array($array2))
      return is_array($array1) ? $array1 : (is_array($array2) ? $array2 : array());
    reset ($array2);
    while(key($array2)!==null) {
      if (is_array(current($array2)) && isset($array1[key($array2)])) {
        $array1[key($array2)] = ArrayUtils::merge_recursive_overwrite($array1[key($array2)],current($array2));
      } else {
        $array1[key($array2)] = current($array2);
      }
      next($array2);
    }
    return $array1;
  }

  public static function deep_copy($array) {
    return unserialize(serialize($array));
  }

  public static function filter_key() {
    $ret    = array();
    $keys   = func_get_args();
    $array  = array_shift($keys);
    $invert = (count($keys) > 0 && is_bool($keys[count($keys)-1])) 
      ? array_pop($keys) : false;

    foreach ($array as $key => $item)
      if ($invert != in_array($key, $keys))
        $ret[$key] = $item;

    return $ret;
  }

  public static function rename_key($array, $old, $new) {
    $array[$new] = $array[$old];
    unset($array[$old]);
    return $array;
  }

  public static function toArray($array) {
    $map = function($item) {
      if (is_array($item))
        return ArrayUtils::toArray($item);
      else
        return method_exists($item, 'toArray') ? $item->toArray() : $item;
    };
    return array_map($map, $array);
  }

  public static function isAssoc($array) {
    return !empty($array) && is_array($array) && array_values($array) !== $array;
  }

  public static function toKeyValMaps($map) {
    $ret = array();
    foreach ($map as $k => $v)
      $ret[] = array('key' => $k, 'val' => $v);
    return $ret;
  }
}  

