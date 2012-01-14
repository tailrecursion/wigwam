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

  public static function h($argline) {
    global $argv;
    $name = basename($argv[0]);
    $help = <<<EOT

Usage: $name [OPTIONS]

Where OPTIONS are:

  -h          Print usage info and exit.
  -q          Don't echo the result after evaling each expression.
  -z          Run script files but don't start interactive REPL.
  -f <file>   Require() <file> prior to starting REPL.

The following commands are available inside the REPL environment:

  /h          Print usage info.
  /pp         Toggle echoing the result of each eval.
  /p <expr>   Toggle echoing the result just for this expression.
  /f <file>   Require() <file>.

EOT;

    echo "$help\n";
    return '';
  }

  public static function f($argline) {
    $f = trim($argline);
    return "require '$f'";
  }

}
