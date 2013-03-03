<?php namespace Wigwam\Console;

use RuntimeException;
use ReflectionClass;
use ReflectionFunction;

use Wigwam\ClassLoader;

function showDocComment($dc) {
  $dc = preg_replace('/^  */m', ' ', $dc);
  return strlen($dc) ? $dc : "/**\n * No doc comment.\n */";
}

function showMethodSignature($m) {
  return !is_object($m) ? "" : $m->getName() . "(" .
    implode(", ", array_reduce($m->getParameters(), function($xs, $x) {
      $tmp = ($x->isArray() ? "array" : "mixed") . ' ' .
        ($x->isPassedByReference() ? '&' : '') . '$' . $x->getName();
      if ($x->isDefaultValueAvailable())
        $tmp .= ("=" . var_export($x->getDefaultValue(),true));
      if ($x->isOptional())
        $tmp = "[$tmp]";
      $xs[] = $tmp;
      return $xs;
    }, array())) .
  ")";
}

function docForFunction($fn) {
  $r = new ReflectionFunction($fn);
  return showDocComment($r->getDocComment()) . "\n" .
    showMethodSignature($r);
}

function docForClass($class) {
  $r = new ReflectionClass($class);
  return showDocComment($r->getDocComment());
}

function docForClassMethod($class, $v) {
  $r = new ReflectionClass($class);
  switch($v) {
    case "__construct":
      $m = $r->getConstructor();
      break;
    default:
      $m = $r->getMethod($v);
  }
  return showDocComment(is_object($m) ? $m->getDocComment() : "") . "\n" .
    $class . "::" . showMethodSignature($m);
}

function docForClassProperty($class, $v) {
  $v = removeSigil($v);
  $r = new ReflectionClass($class);
  $m = $r->getProperty($v);
  return showDocComment($m->getDocComment());
}

class ConsoleCommand {
  public static $CMD = array();

  public static function add($name, $f) {
    static::$CMD[$name] = $f;
  }

  public static function doit($line) {
    if (! preg_match('/^\\/([^\\s\/]*)(\s+(.*))?$/', $line, $m))
      return $line;

    $cmd = static::$CMD;

    $argline = isset($m[3]) ? $m[3] : null;

    if (array_key_exists($m[1], $cmd))
      return $cmd[$m[1]]($argline);
    elseif (method_exists(get_called_class(), $m[1]))
      return self::$m[1]($argline);

    throw new RuntimeException("No such console command: '{$m[1]}'");
  }

  public static function pp() {
    Console::$print = Console::$printnext = (! Console::$print);
  }

  public static function pager($argline) {
    error_log(var_export($argline, true));
    Console::$PAGER = $argline;
  }

  public static function p($argline) {
    Console::$printnext = (! Console::$printnext);
    return $argline;
  }

  public static function q($argline) {
    Console::$printnext = false;
    return $argline;
  }

  public static function x($argline) {
    Console::$printshortnext = false;
    return $argline;
  }

  public static function h($argline) {
    Console::color();
    $pid = pcntl_fork();
    if (! $pid)
      pcntl_exec(trim(`which man`), array(dirname(__DIR__)."/console.1"));
    elseif ($pid > 0)
      pcntl_wait($status);
    else
      return 'throw new Exception("Can\'t fork.")';
    return '';
  }

  public static function hh($argline) {
    $h = readline_list_history();
    $s = implode("\n", array_map(function($k, $v) {
      return sprintf("%5d  %s", $k, implode("\n       ", explode("\n", $v)));
    }, array_keys($h), array_values($h)));

    if ($s) print("$s\n");

    return '';
  }

  public static function f($argline) {
    $f = trim($argline);
    return "require '$f'";
  }

  public static function d($argline) {
    $T_PREFIX = ConsoleCommandCompletion::T_PREFIX;
    $T_CLASS  = ConsoleCommandCompletion::T_CLASS;

    $c = new ConsoleCommandCompletion(true);
    $c->parseBuf($argline, $t, $v);

    switch ($t) {
      case array(T_STRING):
      case array($T_CLASS):
        error_log(docForClass($v[0]));
        break;
      case array(T_STRING, '(', ')'):
        error_log(docForFunction($v[0]));
        break;
      case array($T_PREFIX, T_STRING):
        error_log(docForClass(implode("\\", $v)));
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

  public static function e($argline) {
    if (! $argline && Console::$SCRIPTFILE)
      $argline = Console::$SCRIPTFILE;

    if (! $argline) {
      echo "No file to edit.\n";
      return;
    }

    $tmpf   = tempnam(sys_get_temp_dir(), "console");
    $tf     = escapeshellarg($tmpf);
    $hf     = escapeshellarg(Console::$HISTFILE_S);
    $sf     = escapeshellarg($argline);
    $editor = getenv("EDITOR") ? getenv("EDITOR") : "vi";

    system("touch $sf");
    system("touch $tf");
    system("cp $sf $tf");
    system("cat $hf |grep -v '^/' >> $tf");

    passthru("$editor $tf");

    echo "Save to $sf? [y/N] ";
    $resp = chop(fgets(STDIN));

    if (preg_match('/y(es)?/i', $resp)) {
      system("mv $tf $sf");
      echo "Saved.\n";
    } else
      echo "Discarded.\n";

    system("rm -f $tf");
  }

}
