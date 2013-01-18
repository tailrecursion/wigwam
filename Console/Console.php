<?php namespace Wigwam\Console;

use \RuntimeException;
use \ReflectionMethod;

function substr_replace_first($str, $find, $replace) {
  return (($pos = strpos($str, $find)) !== false)
    ? substr_replace($str, $replace, $pos, strlen($find))
    : $str;
}

function num_tok($arr) {
  return is_array($arr) && $arr[0] == T_LNUMBER ? $arr[1] : null;
}

/**
 * This is the Console class.
 */
class Console {
 
  /* ANSI terminal color escape */
  public static $colors = array(
    "none"          => -1,
    "black"         => 30,
    "red"           => 31,
    "green"         => 32,
    "yellow"        => 33,
    "blue"          => 34,
    "magenta"       => 35,
    "cyan"          => 36,
    "white"         => 37,
    "bold-black"    => '1;30',
    "bold-red"      => '1;31',
    "bold-green"    => '1;32',
    "bold-yellow"   => '1;33',
    "bold-blue"     => '1;34',
    "bold-magenta"  => '1;35',
    "bold-cyan"     => '1;36',
    "bold-white"    => '1;37',
  );

  public static $PS1_COLOR    = "\033[34m[\033[33m%s\033[34m] [\033[33m%.3fs\033[34m] [\033[33m%.1fMB\033[34m]\033[0m\n>>> ";

  /** Current prompt. */
  public static $n            = 0;
  public static $PS1          = "[%s] [%.3fs] [%.1fMB]\n>>> ";
  public static $PS2          = '  *> ';
  public static $OUTCOLOR     = 36;

  public static $INTERACTIVE  = true;

  public static $HISTORY      = true;
  public static $HISTPREFIX   = "_";
  public static $HISTSIZE     = 1000;

  public static $HISTFILE_S   = "";
  public static $SCRIPTFILE   = "";
  public static $RUNSCRIPT    = array();

  public static $DEBUG        = false;

  public static $getopt       = "f:q";
  public static $option       = array();

  public static $print        = true;
  public static $printnext    = true;

  public static $sock         = array();
  public static $reboot       = false;
  public static $pid1;
  public static $pid2;
  public static $pid3;
  public static $status;
  
  public static $line;
  public static $result;
  public static $no_result;

  public static $tmp;
  public static $tmp_result;
  public static $tmp_status   = array(0,0);
  public static $start_time   = 0.0;
  public static $last_time    = 0.0;
  public static $mem_usage    = 0.0;

  public static $completion_error;
  public static $ignore_errors = false;
  public static $print_before_error_message = "";
  public static $print_after_error_message  = "";

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

  public static function probe() {
    $args = func_get_args();
    $cl   = array_shift($args);
    $meth = array_shift($args);

    $r = new ReflectionMethod($cl, $meth);
    $r->setAccessible(true);

    return call_user_func_array(
      array($r, "invoke"),
      array_merge(array(is_object($cl) ? $cl : null), $args));
  }

  public static function done() {
    Console::$ignore_errors = true;
    die();
  }

  public static function prompt() {
    $p = static::$PS1;
    $n = static::$HISTORY ? static::$n : '';
    $t = static::$last_time;
    $m = static::$mem_usage;
    return is_callable($p) ? $p($n, $t, $m) : sprintf($p, $n, $t, $m);
  }

  public static function strcolor($color="none") {
    $color = $color == "none" ? 0 : static::$colors[$color];
    return static::$OUTCOLOR != -1 ? "\033[{$color}m" : "";
  }

  public static function color($color="none") {
    printf(static::strcolor($color));
  }

  public static function errMsg($message, $file, $line) {
    return sprintf(
      "%sPHP [%d]: %s\nIn %s line %d%s",
      Console::$print_before_error_message,
      posix_getpid(),
      $message,
      $file,
      $line,
      Console::$print_after_error_message
    );
  }

  public static function printException($e) {
    Console::printErr(
      get_class($e).": ".$e->getMessage(),
      $e->getFile(),
      $e->getLine()
    );
  }

  public static function printErrData($data) {
    Console::color("red");
    error_log(var_export($data, true));
    Console::color();
  }

  public static function printErr($message, $file, $line) {
    Console::color("red");
    error_log(static::errMsg($message, $file, $line));
    Console::color();
  }

  public static function printFatal($e) {
    static::printErr($e['message'], $e['file'], $e['line']);
  }

  public static function setup() {
    global $argv;

    error_reporting(0);

    static::getSocks();
    static::$argv       = $argv;

    readline_read_history(static::getHistFile());

    if (is_writable(dirname(static::$HISTFILE_S)))
      system("> ".escapeshellarg(static::$HISTFILE_S));
    else
      static::$HISTFILE_S = false;

    register_shutdown_function(function() {
      if (! Console::$ignore_errors && $e = error_get_last())
        Console::printFatal($e);
      die();
    });

    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
      Console::printErr($errstr, $errfile, $errline);
      Console::done();
    });

    set_exception_handler(function($e) {
      Console::printException($e);
      Console::done();
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

    for ($i=0; $i<count($toks)-1; $i++) {
      $t1 = $toks[$i];
      $t2 = $toks[$i+1];

      if ($toks[$i] == '$') {
        if (! is_null($n = num_tok($toks[$i+1])))
          return static::printableLine(
            substr_replace_first(
              $line, "\${$n}", "\$_[{$n}]"));
        if ($toks[$i+1] == '$')
          return static::printableLine(
            substr_replace_first(
              $line, "\$\$", "\$_[-1]"));
        if ($i < count($toks)-2 && $toks[$i+1] == '-' &&
          ! is_null($n = num_tok($toks[$i+2])))
          return static::printableLine(
            substr_replace_first(
              $line, "\$-{$n}", "\$_[-{$n}]"));
      }
    }

    static::gobbleWhitespace($toks);

    if (static::$DEBUG)
      error_log(var_export($toks, true));

    if (count($toks) && count($toks[0]) && $toks[0][0] == T_FUNCTION) {
      array_shift($toks);
      static::gobbleWhitespace($toks);
      return $toks[0] == '(';
    }

    return 
      (count($toks) &&
        (count($toks[0]) &&
          (in_array($toks[0][0], static::$printable_tokens) ||
          in_array($toks[0], static::$printable_tokens))))
        ? $line
        : false;
  }

  public static function escapeHist($arg) {
    return addcslashes(addcslashes(escapeshellarg($arg), "\r\n\t"), '\\');
  }

  public static function updateHistFile($line) {
    if (static::$HISTFILE_S)
      system("echo ".static::escapeHist($line)." >> ".static::escapeHist(static::$HISTFILE_S));
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
    $lines  = explode("\n", $prompt);
    $prompt = array_pop($lines);
    echo(implode("\n", $lines)."\n");
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
    if (count(static::$RUNSCRIPT)) {
      $line = array_shift(static::$RUNSCRIPT);
      echo static::prompt()."$line";
    } else
      $line = static::doReadline(static::prompt());
    static::writeSock(0, 0, $line);
  }

  public static function getLine() {
    $line = preg_replace('/;$/', '', static::readSock(0, 1));

    if (static::$completion_error)
      $line = " ";

    $line = ConsoleCommand::doit($line);
    $plin = static::printableLine($line);

    $line = 'Wigwam\Console\Console::$result = '
      . ($plin ? '' : 'Wigwam\Console\Console::$no_result; ')
      . $plin . ';';

    static::$line = $line;

    return $line;
  }

  public static function printResult($res) {
    if (static::$printnext)
      printf(
        ( ($hc = static::$OUTCOLOR) == -1 )
          ? "=> %s\n"
          : "\033[".$hc."m%s\033[0m\n", 
        var_export($res, true)
      );
    static::$printnext = static::$print;
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
    static::$completion_error = false;

    if (! socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sock))
      throw new RuntimeException("can't create socket pair");

    socket_set_nonblock($sock[1]);

    while (($tmp = static::getCompletionReq()) != ':done:') {
      $pid = pcntl_fork();

      if (! $pid) {
        // child

        Console::$print_before_error_message = "\n";

        $forceStatic = preg_match('/^\\/d /', $tmp);
        $c = new ConsoleCommandCompletion($forceStatic);
        static::sendCompletionResp($c->complete($tmp));

        if (socket_write($sock[0], "ok") === false)
          throw new RuntimeException("can't write to socket");
        
        Console::done();
      } else {
        // parent

        pcntl_waitpid($pid, $status, WUNTRACED);

        $buf = socket_read($sock[1], 8192, PHP_BINARY_READ);

        if ($buf != "ok") {
          static::$completion_error = true;
          //static::endCompletions();
          static::sendCompletionResp("ERROR");
        }
      }
    }

  }

  public static function endCompletions() {
    static::sendCompletionReq(':done:');
  }
}
