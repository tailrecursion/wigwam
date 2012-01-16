<?php namespace Wigwam\Console;

use Wigwam\ClassLoader;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

function removeSigil($v) {
  return preg_replace('/^\\$/', '', $v);
}

function mytoken_name($t) {
  switch ($t) {
    case ConsoleCommandCompletion::T_PREFIX:
      return 'T_PREFIX';
      break;
    case ConsoleCommandCompletion::T_CLASS:
      return 'T_CLASS';
      break;
  }
}

function tname($t) {
  return is_int($t)
    ? ($t < 0 ? mytoken_name($t) : token_name($t))
    : $t;
}

function tname_array($t) {
  return array_map(function($x) {
    return tname($x);
  }, $t);
}

class ConsoleCommandCompletion {

  const T_PREFIX = -1;
  const T_CLASS  = -2;

  public $forceStatic;

  public function __construct($forceStatic) {
    $this->forceStatic = $forceStatic;
  }

  public function parseBuf($buf, &$t, &$v) {
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

    static::collapseNamespace($t, $v);
    static::collapseClass($t, $v);
  }

  public function complete($buf) {
    $this->parseBuf($buf, $t, $v);

    switch ($t) {
      case array(T_VARIABLE):
        $v[0] = preg_replace('/^\\$/', '', $v[0]);
        $m = $this->matchVariable($v[0]);
        return $m;

      case array(T_VARIABLE, T_OBJECT_OPERATOR):
        $class  = get_class($GLOBALS[removeSigil($v[0])]);
        return $this->matchObjVarOrMethod($class, '');

      case array(T_VARIABLE, T_OBJECT_OPERATOR, T_STRING):
        $class  = get_class($GLOBALS[removeSigil($v[0])]);
        return $this->matchObjVarOrMethod($class, $v[2]);

      case array(T_STRING):
        return array_merge(
          $this->matchFunction($v[0]),
          $this->matchConstant($v[0]),
          $this->matchClassOrNamespace($v[0], '')
        );

      case array(self::T_CLASS, T_DOUBLE_COLON):
        return $this->matchClassConstStaticMethodOrVarSigil($v[0]);

      case array(self::T_CLASS, T_DOUBLE_COLON, T_STRING):
        return $this->matchClassConstOrStaticMethod($v[0], $v[2]);

      case array(self::T_CLASS, T_DOUBLE_COLON, '$'):
        return $this->matchClassStaticVar($v[0], '');

      case array(self::T_CLASS, T_DOUBLE_COLON, T_VARIABLE):
        $vv = removeSigil($v[2]);
        return $this->matchClassStaticVar($v[0], $vv);

      case array(self::T_PREFIX):
        return $this->matchClassOrNamespace(array(''), $v[0]);

      case array(self::T_PREFIX, T_STRING):
        return $this->matchClassOrNamespace($v[1], $v[0]);
    }
  }

  private function collapseNamespace(&$t, &$v) {
    $rt = array();
    $rv = array();
    $ns = array();
    while (count($t) > 1 && $t[0] == T_STRING && $t[1] == T_NS_SEPARATOR) {
      array_push($ns, $v[0]);
      array_splice($t, 0, 2);
      array_splice($v, 0, 2);
    }
    if (count($ns)) {
      array_unshift($t, self::T_PREFIX);
      array_unshift($v, implode('\\', $ns));
    }
  }

  private function collapseClass(&$t, &$v) {
    if (count($t) >= 3 && $t[0] == self::T_PREFIX && $t[1] == T_STRING
      && $t[2] == T_DOUBLE_COLON) {
      array_splice($t, 0, 2, self::T_CLASS);
      array_splice($v, 0, 2, "{$v[0]}\\{$v[1]}");
    }
    elseif (count($t) >= 2 && $t[0] == T_VARIABLE && $t[1] == T_DOUBLE_COLON) {
      $t[0] = self::T_CLASS;
      $v[0] = $GLOBALS[removeSigil($v[0])];
    }
    elseif (count($t) >= 2 && $t[0] == T_STRING && $t[1] == T_DOUBLE_COLON)
      $t[0] = self::T_CLASS;
  }

  private function match($v, $arr) {
    $pat = '/^'.preg_quote($v, '/').'/';
    return array_filter($arr, function($x) use ($pat) {
      return preg_match($pat, $x);
    });
  }

  private function matchObjVarOrMethod($class, $v) {
    return array_merge(
      $this->matchObjVar($class, $v),
      $this->matchObjMethod($class, $v)
    );
  }

  private function matchObjVar($class, $v) {
    $r = new ReflectionClass($class);
    $m = array_filter(array_map(function($x) {
      return $x->isStatic() ? null : $x->getName();
    }, $r->getProperties(ReflectionProperty::IS_PUBLIC)));

    return $this->match($v, $m);
  }

  private function matchObjMethod($class, $v) {
    $r = new ReflectionClass($class);
    $m = array_filter(array_map(function($x) {
      return $x->isStatic() ? null : $x->getName()."()";
    }, $r->getMethods(ReflectionMethod::IS_PUBLIC)));

    $h = $r->implementsInterface('Wigwam\\Console\\ConsoleCompletionHelper')
      ? call_user_func(array($class, 'completeMethod'))
      : array();

    return array_merge($h, $this->match($v, $m));
  }

  private function matchClassStaticVar($class, $v) {
    $r      = new ReflectionClass($class);
    $xthis  = $this;
    $m = array_filter(array_map(function($x) use ($xthis) {
      return $x->isStatic() || $xthis->forceStatic ? $x->getName() : null;
    }, $r->getProperties(ReflectionProperty::IS_PUBLIC)));

    return $this->match($v, $m);
  }

  private function matchClassStaticVarSigil($class) {
    $c = preg_replace('/^.*\\\\/', '', $class);
    return array_map(function($x) use ($c) {
      return "$c::\$$x";
    }, $this->matchClassStaticVar($class, ''));
  }

  private function matchClassConstStaticMethodOrVarSigil($class) {
    return array_merge(
      $this->matchClassConstOrStaticMethod($class, ''),
      $this->matchClassStaticVarSigil($class)
    );
  }

  private function matchClassConstOrStaticMethod($class, $v) {
    return array_merge(
      $this->matchClassConst($class, $v),
      $this->matchStaticMethod($class, $v)
    );
  }

  private function matchClassConst($class, $v) {
    $c = preg_replace('/^.*\\\\/', '', $class);
    $r = new ReflectionClass($class);
    $m = array_keys($r->getConstants());

    $m = $this->match($v, $m);

    return array_map(function($x) use ($c) {
      return "$c::$x";
    }, $m);
  }

  private function matchStaticMethod($class, $v) {
    $c      = preg_replace('/^.*\\\\/', '', $class);
    $r      = new ReflectionClass($class);
    $xthis  = $this;

    $m = array_filter(array_map(function($x) use ($xthis) {
      return $x->isStatic() || $xthis->forceStatic ? $x->getName() : null;
    }, $r->getMethods(ReflectionMethod::IS_PUBLIC)));

    $h = $r->implementsInterface('Wigwam\\Console\\ConsoleCompletionHelper')
      ? call_user_func(array($class, 'completeMethodStatic'))
      : array();

    $m = array_merge($h, $this->match($v, $m));

    return array_map(function($x) use ($c) {
      return "$c::$x()";
    }, $m);
  }

  private function matchClassOrNamespace($v, $prefix) {
    return array_merge(
      $this->matchClass($v[0], $prefix),
      $this->matchNamespace($v[0], $prefix)
    );
  }

  private function matchNamespace($v, $prefix) {
    return array_map(function($x) {
      return "$x\\";
    }, $this->match($v, ClassLoader::listNamespacesInNamespace($prefix)));
  }

  private function matchClass($v, $prefix) {
    $d = array_filter(array_map(function($x) {
      return preg_match('/\\\\/', $x) ? null : $x;
    }, get_declared_classes()));
    return $this->match($v, ClassLoader::listClassesInNamespace($prefix));
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
    $v = removeSigil($v);
    return array_map(function($x) {
      return "\$$x";
    }, $this->matchVariable($v));
  }

  private function matchVariable($v) {
    return $this->match($v, array_keys($GLOBALS));
  }

}
