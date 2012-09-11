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

  -c <color>    Return value print color (default is "cyan"). Choices are:
                [black, red, green, yellow, blue, magenta, cyan, white, none]
                Choose "none" to disable colored output.
  -f <file>     Require <file> before starting REPL.
  -h            Print usage info and exit.
  -H            Don't parse .htaccess files at startup.
  -i <var=val>  Set PHP configuration option "var" to "val".
  -j <prefix>   Prefix for PHP history globals (default is "_").
  -J            Disable PHP history globals.
  -p <file>     Require <file> before loading console's classloader.
  -q            Don't echo the result after evaling each expression.
  -s <file>     Run console commands in <file> before interactive REPL.
  -v <var=val>  Set \$var to "val" globally.
  -z            Run script files but don't start interactive REPL.

  Multiple -f, -i, -p, and -v options may be specified on the same command line.

THE REPL ENVIRONMENT

  The following commands are available inside the REPL environment:

  /d <thing>    Get the doc comment for the <thing>.
  /e [file]     Append session history to <file> and open in editor. If <file>
                is not specified then the file specified with the -s option
                will be used.
  /f <file>     Require() <file>.
  /h            Print usage info.
  /p <expr>     Toggle echoing the result just for this expression.
  /pp           Toggle echoing the result of each eval.
  /q <expr>     Disable echoing the result of this expression.

  Console history globals:

  If PHP history globals are enabled (i.e. the -J option is not specified),
  the expressions evaluated by the repl are numbered, starting from 1. The
  current expression number is displayed in the prompt. The result of the
  expression is recorded in a global variable named \$_<num>, where <num> is
  the expression number. Additionally, the result of the previous expression
  is always assigned to \$_.

  If the underscore-prefixed history globals clobber something in your
  environment, you can change the prefix to something else using the -j option,
  or you can disable them completely with -J.

EOT;

    echo "$help\n";
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
