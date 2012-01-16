<?php namespace Wigwam\Console;

use RuntimeException;
use ReflectionClass;

function showDocComment($dc) {
  $dc = preg_replace('/^  */m', ' ', $dc);
  return strlen($dc) ? $dc : "No docs available.";
}

function docForClass($class) {
  $r = new ReflectionClass($class);
  return showDocComment($r->getDocComment());
}

function docForClassMethod($class, $v) {
  $r = new ReflectionClass($class);
  $m = $r->getMethod($v);
  return showDocComment($m->getDocComment());
}

function docForClassProperty($class, $v) {
  $v = removeSigil($v);
  $r = new ReflectionClass($class);
  $m = $r->getProperty($v);
  return showDocComment($m->getDocComment());
}

class ConsoleCommand {

  public static function doit($line) {
    if (! preg_match('/^\\/([^\\s\/]*)(\s+(.*))?$/', $line, $m))
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

  public static function q($argline) {
    Console::$printnext = false;
    return $argline;
  }

  public static function h($argline) {
    global $argv;
    $name = basename($argv[0]);
    $help = <<<EOT

Usage: $name [OPTIONS]

Where OPTIONS are:

  -h            Print usage info and exit.
  -q            Don't echo the result after evaling each expression.
  -z            Run script files but don't start interactive REPL.
  -f <file>     Require() <file> prior to starting REPL.
  -v <var=val>  Set \$var to "val" globally.

The following commands are available inside the REPL environment:

  /h            Print usage info.
  /pp           Toggle echoing the result of each eval.
  /p <expr>     Toggle echoing the result just for this expression.
  /q <expr>     Disable echoing the result of this expression.
  /f <file>     Require() <file>.
  /d <thing>    Get the doc comment for the <thing>.

EOT;

    echo "$help\n";
    return '';
  }

  public static function f($argline) {
    $f = trim($argline);
    return "require '$f'";
  }

  public static function d($argline) {
    $T_CLASS = ConsoleCommandCompletion::T_CLASS;
    $c = new ConsoleCommandCompletion(true);
    $c->parseBuf($argline, $t, $v);

    switch ($t) {
      case array(T_STRING):
      case array($T_CLASS):
        error_log(docForClass($v[0]));
        break;
      case array($T_CLASS, T_DOUBLE_COLON, T_STRING, '(', ')'):
        error_log(docForClassMethod($v[0], $v[2]));
        break;
      case array($T_CLASS, T_DOUBLE_COLON, T_VARIABLE):
        error_log(docForClassProperty($v[0], $v[2]));
        break;
      default:
        error_log("No docs available: query isn't a class, property, or method.");
    }

    return '';
  }

}
