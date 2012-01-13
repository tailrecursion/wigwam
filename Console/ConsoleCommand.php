<?php namespace Wigwam\Console;

use RuntimeException;

class ConsoleCommand {

  public static function doit($line) {
    if (! preg_match('/^\\/([^\\s]*)(\s+(.*))?$/', $line, $m))
      return $line;

    $argline = isset($m[3]) ? $m[3] : null;

    if (method_exists(get_called_class(), $m[1]))
      return self::$m[1]($argline);

    throw new RuntimeException("No such console command: '{$m[1]}'");
  }

  public static function pp() {
    Console::$print = Console::$printnext = (! Console::$print);
  }

  public static function p($argline) {
    Console::$printnext = (! Console::$printnext);
    return $argline;
  }

}
