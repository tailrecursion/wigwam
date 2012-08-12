<?php namespace Wigwam;

use \ReflectionClass;
use \ReflectionMethod;

class Reflect {

  private $name;
  private $api;
  private $taghlrs;
  private $parsehlrs;

  public function __construct($name) {
    $xthis            = $this;
    $this->name       = $name;
    $this->taghlrs    = array();
    $this->parsehlrs  = array();
  }

  public function getName() {
    $app = new ReflectionClass($this->name);
    return $app->getName();
  }

  public function addTagHandler($tag, $handler) {
    $this->taghlrs[$tag] = $handler;
  }

  public function addParseHandler($handler) {
    array_push($this->parsehlrs, $handler);
  }

  public function run() {
    return $this->getApi();
  }

  public function getApi() {
    $app    = new ReflectionClass($this->name);
    $xthis  = $this;

    return $this->api ? $this->api : $this->api = array(
      'name'      => $app->getName(),
      'methods'   => array_values(array_map(function($method) use ($xthis) {
        return $method->isPublic()
          ? $xthis->runParseHandlers(array(
              'name'      => $method->getName(),
              'tags'      => $xthis->parseDoc((string) $method->getDocComment()),
              'params'    => array_map(function($param) {
                $tmp = array(
                  'name'      => $param->name,
                  'optional'  => $param->isOptional()
                );
                if ($param->isDefaultValueAvailable())
                  $tmp['default'] = $param->getDefaultValue();
                return $tmp;
              }, $method->getParameters())
            ))
          : false;
      }, $app->getMethods(ReflectionMethod::IS_STATIC)))
    );
  }

  public function runParseHandlers($method) {
    return array_reduce($this->parsehlrs, function($xs, $x) {
      return $xs = $x($xs);
    }, $method);
  }

  public function parseDoc($doc) {
    $xthis = $this;
    return array_reduce(array_keys($this->taghlrs), function($acc, $e) use ($doc, $xthis) {
      $acc[$e] = array_values(array_filter(array_map(function($line) use ($xthis, $e) {
        $toks  = preg_split('/ +/', preg_replace('/^[\s\*]+(@[^\s]+.*)\s*$/', '\1', $line));
        return array_shift($toks) == "@$e"
          ? array_map(function($tok) use ($xthis) {
              return $xthis->parseDocTag($tok);
            }, $toks)
          : null;
      }, preg_split('/\n/', $doc))));
      return $acc;
    }, array());
  }

  public function parseDocTag($tok) {
    $name   = preg_replace('/\(.*/', '', $tok);
    $argstr = preg_split('/,/', preg_replace('/^.*\((.*)\).*$/', '$1', $tok));

    $args = ($name == $tok) ? array() : array_values(array_map(function($arg) {
      return preg_replace('/^\$/', '', $arg);
    }, array_filter($argstr)));

    return array('name'=>$name, 'args'=>$args);
  }

  public function apply($method, $params) {
    $api      = $this->getApi();
    $xthis    = $this;
    $taghlrs  = $this->taghlrs;

    if ($method == 'getApi') return $this->getApi();

    // The method's API specification.
    $meth = array_reduce($api['methods'], function($acc, $e) use ($method) {
      return !$acc && $e['name'] == $method ? $e : $acc;
    });

    if (!$meth)
      throw new BadArgument("Method not found: $method");

    // The argument list for the method that will be called.
    $args = array_map(function($spec) use ($params) {
      if (!array_key_exists($spec['name'], $params))
        throw new BadArgument("Missing non-optional arg: ".$spec['name']);
      return $params[$spec['name']];
    }, $meth['params']);

    array_map(function($e) use ($taghlrs, $meth, $params) {
      if (array_key_exists($e, $taghlrs)) {
        $tags = array_map(function($line) use ($params) {
          return array_map(function($tag) use ($params) {
            return array(
              'name'  => $tag['name'],
              'args'  => array_map(function($param) use ($params) {
                return array_key_exists($param, $params)
                  ? $params[$param] : $param;
              }, $tag['args']),
            );
          }, $line);
        }, $meth['tags'][$e]);

        if ($tags) $taghlrs[$e]->run($meth, $tags);
      }
    }, array_keys($meth['tags']));
    
    return call_user_func_array(array($this->name, $method), $args);
  }

}
