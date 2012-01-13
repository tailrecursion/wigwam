<?php namespace Wigwam\Console;

use \RuntimeException;

class Console {
 
  public static $PS1    = 'php> ';
  public static $PS2    = '  *> ';
  public static $DEBUG  = false;

  public static $getopt = "f:q";
  public static $option = array();

  public static $print      = true;
  public static $printnext  = true;

  public static $sock   = array();
  public static $reboot = false;
  public static $pid1;
  public static $pid2;
  public static $pid3;
  public static $status;
  public static $completer;
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
    ini_set('error_log', '');

    static::$argv = $argv;

    register_shutdown_function(function() {
      if ($e = error_get_last())
        error_log("ERROR: {$e['message']}\nIn {$e['file']} line {$e['line']}");
    });

    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
      error_log("ERROR: $errstr\nIn $errfile line $errline");
    });

    set_exception_handler(function($e) {
      printf("%s: %s\nIn %s line %s\n",
        get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
    });

    readline_completion_function(function($buf, $i) {
      $orig   = $buf;
      $info   = readline_info();
      $buf    = substr($info['line_buffer'], 0 , $info['point']);

//      error_log("\norig='$orig'");

      // Prevent empty completion request from being sent, as it would cause
      // the socket to block forever waiting for the response.
      if (! $buf) return;

      $match  = Console::getCompletionResp(Console::sendCompletionReq($buf));

      if (is_array($match) && count($match)) {
        sort($match);
        return $match;
      }
    });

    readline_read_history(static::getHistFile());
    static::$completer = new ConsoleCommandCompletion();
    static::getSocks();
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

  public static function getReflection($thing) {
    switch (true) {
      case is_array($thing):
        return $thing;

      case is_object($thing):
        return new ReflectionObject($thing);

      case class_exists($thing):
        return new ReflectionClass($thing);

      case function_exists($thing):
        return new ReflectionFunction($thing);

      case strstr($thing, '::'):
        list($class, $what) = explode('::', $thing);
        $rc = new ReflectionClass($class);

        switch (true) {
          case substr($what, -2) == '()':
            $what = substr($what, 0, strlen($what) - 2);

          case $rc->hasMethod($what):
            return $rc->getMethod($what);

          case substr($what, 0, 1) == '$':
            $what = substr($what, 1);

          case $rc->hasProperty($what):
            return $rc->getProperty($what);

          case $rc->hasConstant($what):
            return $rc->getConstant($what);
        }

      case is_string($thing):
      case is_numeric($thing):
      case $thing == true:
      case $thing == false:
        return $thing;
    }
  }

  public static function getReflectionString($thing) {
    return var_export(
      is_string($thing) || is_object($thing)
        ? $thing 
        : static::getReflection($thing),
      true
    );
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

  public static function doReadline($prompt) {
    $line = readline($prompt);

    if ($line === false)
      static::$reboot = true;

    if (strlen($line)) {
      readline_add_history($line);
      readline_write_history(static::getHistFile());
    } else
      $line = ' ';
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
    $line = preg_replace('/;$/', '', static::readSock(0, 1));
    $line = ConsoleCommand::doit($line);

    if ($line && static::$printnext && static::printableLine($line))
      $line = 'printf("=> %s\n", Wigwam\Console\Console::getReflectionString('.$line.'))';
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
    while (($tmp = static::getCompletionReq()) != ':done:')
      static::sendCompletionResp(static::$completer->complete($tmp));
  }

  public static function endCompletions() {
    static::sendCompletionReq(':done:');
  }
}
