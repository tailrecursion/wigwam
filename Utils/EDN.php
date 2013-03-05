<?php namespace Wigwam\Utils;

use Wigwam\Utils\ArrayUtils;
use InvalidArgumentException;

class EDN {

  public static function map_to_edn($m) {
    $entries = array_map(function($kv) {
        return sprintf('%s %s', EDN::to_edn($kv['key']), EDN::to_edn($kv['val']));
      },
      ArrayUtils::toKeyValMaps($m));
    return sprintf('{%s}', implode(', ', $entries));
  }

  public static function vector_to_edn($v) {
    return sprintf('[%s]', implode(', ', array_map(array('Wigwam\Utils\EDN', 'to_edn'), $v)));
  }

  public static function date_to_edn($d) {
    return sprintf('#inst %s', $d->format($d::RFC3339));
  }

  public static function to_edn($o) {
    if (is_array($o)) {
      /* Arrays */
      return ArrayUtils::isAssoc($o) ?
        EDN::map_to_edn($o) :
        EDN::vector_to_edn($o);
    } elseif (is_object($o)) {
      /* Arbitrary objects */
      switch (get_class($o)) {
      case 'DateTime':
        return EDN::date_to_edn($o);
      default:
        $exMsg = sprintf("EDN: Don't know how to print class %s", get_class($o));
        throw new InvalidArgumentException($exMsg);
      }
    } else {
      /* Strings and primitives */
      if (is_string($o)) {
        return sprintf('"%s"', $o);
      } else {
        return ((string)$o);
      }
    }
  }
}
