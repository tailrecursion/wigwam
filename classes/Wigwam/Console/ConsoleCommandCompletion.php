<?php namespace Wigwam\Console;

use Wigwam\ClassLoader;
use \RecursiveIteratorIterator;

class ConsoleCommandCompletion {

  public $classes;

  public function complete($buf) {
    $tok  = token_get_all("<?php $buf");

    array_shift($tok); //remove the '<?php' token
    Console::gobbleWhitespace($tok);

    if (! count($tok))
      return;
    
    for (
      $tok2=array_reverse($tok), $tok=array();
      count($tok2) && (!count($tok2[0]) || $tok2[0][0] != T_WHITESPACE);
      array_unshift($tok, array_shift($tok2))
    );

    $t = array_reduce($tok, function($xs, $x) {
      $xs[] = is_array($x) ? $x[0] : $x;
      return $xs;
    }, array());

    $v = array_reduce($tok, function($xs, $x) {
      $xs[] = is_array($x) ? $x[1] : $x;
      return $xs;
    }, array());

    if ($t == array(T_VARIABLE)) {
      $v[0] = preg_replace('/^\\$/', '', $v[0]);
      $m = $this->matchVariable($v[0]);
      return $m;
    }

    if ($t == array(T_STRING)) {
      $m1 = $this->matchFunction($v[0]);
      $m2 = $this->matchConstant($v[0]);
      return array_merge($m1, $m2);
    }
  }

  private function match($v, $arr) {
    $pat = '/^'.preg_quote($v, '/').'/';
    return array_filter($arr, function($x) use ($pat) {
      return preg_match($pat, $x);
    });
  }

  private function matchConstant($v) {
    return $this->match($v, array_keys(get_defined_constants()));
  }

  private function matchFunction($v) {
    $fns = get_defined_functions();
    $f1  = $this->match($v, $fns['internal']);
    $f2  = $this->match($v, $fns['user']);
    return array_map(function($x) {
      return "$x()";
    }, array_merge($f1, $f2));
  }

  private function matchVariableSigil($v) {
    $v = preg_replace('/^\\$/', '', $v);
    return array_map(function($x) {
      return "\$$x";
    }, $this->matchVariable($v));
  }

  private function matchVariable($v) {
    return $this->match($v, array_keys($GLOBALS));
  }

}
