<?php namespace Wigwam\Console;

use \RuntimeException;

/**
 * This is the Console class.
 */
class Console {
 
  /* ANSI terminal color escape */
  public static $colors = array(
    "black"   => 30,
    "red"     => 31,
    "green"   => 32,
    "yellow"  => 33,
    "blue"    => 34,
    "magenta" => 35,
    "cyan"    => 36,
    "white"   => 37,
  );

  /** Current prompt. */
  public static $n          = 0;
  public static $PS1        = "\033[1mPHP>\033[37m\033[0m ";
  public static $PS2        = '  *> ';
  public static $OUTCOLOR   = 36;
  public static $HISTORY    = true;
  public static $HISTPREFIX = "_";
  public static $HISTSIZE   = 1000;
  public static $DEBUG      = false;

  public static $getopt     = "f:q";
  public static $option     = array();

  public static $print      = true;
  public static $printnext  = true;

  public static $sock       = array();
  public static $reboot     = false;
  public static $pid1;
  public static $pid2;
  public static $pid3;
  public static $status;
  public static $tmp;

  public static $argv;

  private static $printable_tokens = array(
    '!',
    '+',
    '-',
    '(',
    '~',
    '@',
    '`',
    T_ARRAY,
    T_ARRAY_CAST,
    T_BOOL_CAST,
    T_CLASS_C,
    T_CLONE,
    T_CONSTANT_ENCAPSED_STRING,
    T_DEC,
    T_DIR,
    T_DNUMBER,
    T_DOUBLE_CAST,
    T_EMPTY,
    T_ENCAPSED_AND_WHITESPACE,
    T_FILE,
    T_FUNC_C,
    T_INC,
    T_INT_CAST,
    T_ISSET,
    T_LINE,
    T_LIST,
    T_LNUMBER,
    T_METHOD_C,
    T_NS_C,
    T_NEW,
    T_NUM_STRING,
    T_OBJECT_CAST,
    T_PRINT,
    T_STRING,
    T_STRING_CAST,
    T_STRING_VARNAME,
    T_UNSET_CAST,
    T_VARIABLE,
  );

  public static function printFatal($e) {
    error_log("ERROR: {$e['message']}\nIn {$e['file']} line {$e['line']}");
  }

  public static function setup() {
    global $argv;

    error_reporting(0);

    static::getSocks();
    static::$argv       = $argv;

    readline_read_history(static::getHistFile());

    register_shutdown_function(function() {
      if ($e = error_get_last())
        error_log("ERROR: {$e['message']}\nIn {$e['file']} line {$e['line']}");
      die();
    });

    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
      error_log("ERROR: $errstr\nIn $errfile line $errline");
      die();
    });

    set_exception_handler(function($e) {
      printf("%s: %s\nIn %s line %s\n",
        get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
      die();
    });

    readline_completion_function(function($buf, $i) {
      $orig   = $buf;
      $info   = readline_info();
      $buf    = substr($info['line_buffer'], 0 , $info['point']);

      // Prevent empty completion request from being sent, as it would cause
      // the socket to block forever waiting for the response.
      if (! $buf) return;

      $match  = Console::getCompletionResp(Console::sendCompletionReq($buf));

      if (is_array($match) && count($match)) {
        sort($match);
        return $match;
      }
    });
  }

  public static function fork(&$pid) {
    if (($pid = pcntl_fork()) < 0)
      throw new RuntimeException("can't fork");
  }

  public static function getSocks() {
    if (! socket_create_pair(AF_UNIX, SOCK_STREAM, 0, static::$sock[0]))
      throw new RuntimeException("can't create socket pair");
    if (! socket_create_pair(AF_UNIX, SOCK_STREAM, 0, static::$sock[1]))
      throw new RuntimeException("can't create socket pair");
  }

  public static function readSock($i, $j) {
    $buflen = 8192;
    $sock   = static::$sock[$i][$j];
    $bin    = PHP_BINARY_READ;

    for($ret='';;) {
      if (($buf = socket_read($sock, $buflen, $bin)) === false)
        throw new RuntimeException("can't read from socket");
      $ret.=$buf;
      if ($buf == '' || strlen($buf) < $buflen)
        break;
    }

    return $ret;
  }

  public static function writeSock($i, $j, $buf) {
    if (socket_write(static::$sock[$i][$j], $buf) === false)
      throw new RuntimeException("can't write to socket");
  }

  public static function getHistFile() {
    return $_SERVER['HOME']."/.console.php.history";
  }

  public static function gobbleWhitespace(&$toks) {
    while (count($toks) && count($toks[0]) && $toks[0][0] == T_WHITESPACE) {
      array_shift($toks);
    }
  }

  public static function printableLine($line) {
    $toks = token_get_all('<?php '.$line);
    if (! count($toks))
      return;

    array_shift($toks);
    static::gobbleWhitespace($toks);

    if (static::$DEBUG)
      error_log(var_export($toks, true));

    if (count($toks) && count($toks[0]) && $toks[0][0] == T_FUNCTION) {
      array_shift($toks);
      static::gobbleWhitespace($toks);
      return $toks[0] == '(';
    }

    return count($toks)
      ? (count($toks[0])
          ? in_array($toks[0][0], static::$printable_tokens)
          : in_array($toks[0], static::$printable_tokens))
      : false;
  }

  public static function updateHistFile($line) {
    readline_add_history($line);
    static::writeHistFile();
  }

  public static function writeHistFile() {
    $f    = static::getHistFile();
    $siz  = static::$HISTSIZE;

    if (! readline_write_history($f))
      return;

    // Prevent history file from growing arbitrarily large.

    // NOTE: This code is specific to libedit, possibly. It may or may not
    // work correctly with libreadline. Specifically, libedit seems to need to
    // have '_HiStOrY_V2_' as the first line of the file.

    $head     = `head -n 1 '$f'`;
    $trimmed  = `tail -n $siz '$f'`;
    file_put_contents($f, "$head$trimmed");
  }

  public static function doReadline($prompt) {
    $line = readline($prompt);

    if ($line === false)
      static::$reboot = true;

    if (strlen($line))
      static::updateHistFile($line);
    else
      $line = ' ';

    if (static::$reboot)
      $line = '/q Wigwam\\Console\\Console::$reboot = true';

    return $line;
  }

  public static function stopWorkers() {
    static::writeSock(0, 0, ":done:");
    static::writeSock(1, 0, ":done:");
  }

  public static function readLine() {
    static::writeSock(0, 0, static::doReadline(static::$PS1));
  }

  public static function getLine() {
    $line   = preg_replace('/;$/', '', static::readSock(0, 1));
    $line   = ConsoleCommand::doit($line);
    $hp     = static::$HISTPREFIX;
    $hn     = ++static::$n;
    $hv     = "{$hp}{$hn}";
    $hl     = '$GLOBALS["'.$hp.'"] = $GLOBALS["'.$hv.'"] = ';
    $hc     = static::$OUTCOLOR;

    $prompt = static::$HISTORY ? "\\\$$hv " : "";
    $expr   = (static::$HISTORY ? $hl : "").$line;

    if ($line && static::$printnext && static::printableLine($line))
      $line = 'printf("\033['.$hc.'m%s\n\033[0;1m// %d\033[0m\n", var_export('.$expr.', true), '.$hn.')';

    $line .= ';';

    static::$printnext = static::$print;

    return $line;
  }

  public static function getStatus() {
    return static::readSock(0, 0);
  }

  public static function sendStatus($status) {
    static::writeSock(0, 1, $status);
  }

  public static function sendCompletionReq($buf) {
    static::writeSock(1, 0, $buf);
  }

  public static function getCompletionReq() {
    return static::readSock(1, 1);
  }

  public static function sendCompletionResp($match) {
    static::writeSock(1, 1, serialize($match));
  }

  public static function getCompletionResp() {
    return unserialize(static::readSock(1, 0));
  }

  public static function serviceCompletionRequests() {
    while (($tmp = static::getCompletionReq()) != ':done:') {
      $forceStatic = preg_match('/^\\/d /', $tmp);
      $c = new ConsoleCommandCompletion($forceStatic);
      static::sendCompletionResp($c->complete($tmp));
    }
  }

  public static function endCompletions() {
    static::sendCompletionReq(':done:');
  }
}
