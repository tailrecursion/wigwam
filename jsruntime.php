<?php

use Wigwam\Utils\ArrayUtils;
use Wigwam\ClassLoader;

function getExceptionDefs() {
  $ret      = array();
  $dir      = dirname(__FILE__);
  $files    = array('Wigwam.Exception' => 'Error');
  $classes  = ClassLoader::listClassesInNamespace('Wigwam');

  $dag = array_reduce($classes, function($dag, $class) use (&$files) {
    if ($class == 'Exception')
      return $dag;

    $cls = 'Wigwam\\'.$class;

    if (class_exists($cls, false) || class_exists($cls)) {
      $r = new ReflectionClass($cls);

      if ($r->isSubclassOf('Wigwam\\Exception')) {
        $p      = $r->getParentClass()->name;
        $lhs    = preg_replace('/\\\\/', '.', $cls);
        $rhs    = preg_replace('/\\\\/', '.', $p);

        if (!array_key_exists($rhs, $dag))
          $dag[$rhs] = array($lhs);
        else
          $dag[$rhs][] = $lhs;

        if (!array_key_exists($lhs, $dag))
          $dag[$lhs] = array();

        $files[$lhs] = $rhs;
      }
    }

    return $dag;
  }, array('Error' => array('Wigwam.Exception')));

  return join("\n", array_filter(array_map(function($x) use ($files) {
    if (array_key_exists($x, $files))
      return sprintf("  %-22s = makeErrClass(%s, '%s');", $x, $files[$x], $x);
  }, ArrayUtils::tsort($dag))));
}

?>

/**
 * Wigwam web framework runtime JS.
 *
 * Fork me on github: https://github.com/micha/wigwam
 */
(function() {

  var api   = <?php echo str_replace('\\/', '/', json_encode($api)) ?>;
  var base  = "<?php echo dirname($_SERVER['REQUEST_URI']) ?>";

  function nestedObj(a, b, c) {
    var x = c,
        k = a.pop();
    $.each(a, function(i,v) {
      if (!x[v]) x[v] = {};
      x = x[v];
    });
    x[k] = b;
  };

  function getConf() {
    var ret={},query,i,pair;

    query = $('script')
      .last()
      .attr('src')
      .replace(/^[^\?]*\??/, '')
      .split('&');

    $.each(query, function(i,v) {
      pair = query[i].split('=');
      ret[decodeURIComponent(pair.shift())] =decodeURIComponent(pair.pop());
    });

    ret.base = base;
    ret.argv = getArgv();
    ret.api  = api;

    return ret;
  }

  function getArgv() {
    var q={};
    
    $.each(
      window.location.search.replace(/^\?/,'').split('&'),
      function(i,v) {
        var p=v.split('=');
        q[decodeURIComponent(p[0])]=decodeURIComponent(p[1]);
      }
    );

    return q;
  }

  function makeApi(api) {
    $.each(api, function(i, app) {
      var base = app.name.split('\\');
      $.each(app.methods, function(i, method) {
        var doAsync, doSync;

        doAsync = function() {
          var argv=Array.prototype.slice.call(arguments), 
              data={}, ret;

          $.each(method.params, function(i,v) {
            data[v.name] = (argv.length == 1 && $.type(argv[0]) == "object")
              ? argv[0][v.name]
              : argv[i];
          });

          return function(success, error, sync) {
            return Wigwam.ajax(
              method.verb,
              method.route,
              data,
              success,
              error,
              !sync
            );
          };
        };

        doSync = function() {
          var argv=Array.prototype.slice.call(arguments), 
              ret, ex;

          doAsync.apply(window, argv)(
            function(data) { ret = data },
            function(err) { ex = err },
            true
          );

          if (ex) throw ex;
          return ret;
        };

        nestedObj(base.concat([method.name]), doAsync, window);
        nestedObj(base.concat([method.name]), doSync, window.Wigwam.sync);
      });
    });
  }

  function makeErrClass(proto, type) {
    var F = function(data) { $.extend(this, data); this.type = type };
    F.prototype = new proto();
    return F;
  }

  window.Wigwam = {
    data: {},

    csrfToken: 0,

    cfg: getConf(),

    ajax: function(method, url, data, callback, errcallback, async) {
      var argv = Array.prototype.slice.call(arguments), process, opt;

      if ((method = method.toUpperCase()) == 'GET' || method == 'POST')
        process = true;
      else
        data = JSON.stringify(data);
      
      opts = {
        async:        async,
        type:         method,
        processData:  process,
        dataType:     'json',
        url:          Wigwam.cfg.base+url,
        data:         data,
        accepts: {
          json: 'application/json'
        },
        headers: {
          'X-CSRFToken': Wigwam.csrfToken
        },
        success: function(data, textStatus, xhr) {
          Wigwam.csrfToken = xhr.getResponseHeader('X-CSRFToken');
          if ($.isFunction(callback))
            callback(data);
        },
        error: function(xhr,stat,err) {
          var body  = JSON.parse(xhr.responseText),
              token = body ? body.token : '',
              e     = (body && body.exception)
                        ? eval(body.exception.replace(/\\/, '.')) 
                        : undefined;
          Wigwam.csrfToken = xhr.getResponseHeader('X-CSRFToken');
          e = e ? new e(body) : new Error(err);
          if (e instanceof Wigwam.BadCSRFToken) {
            Wigwam.csrfToken = body.token;          
            Wigwam.ajax.apply(window, argv);
          } else if ($.isFunction(errcallback)) {
            errcallback(e);
          }
        }
      };

      if (!process)
        opts.contentType = 'application/json';

      $.ajax(opts);
    },

    onError: function() {
      var argv=Array.prototype.slice.call(arguments), 
          dmsg="An error occurred. Please try again. Please call\n"
            +"customer service if this problem persists.";
      return function(err) {
        var x, msg;
        while (x = argv.shift()) {
          if ($.type(x) === 'string')
            dmsg = x;
          else if (err instanceof x)
            return alert($.type(msg = argv.shift()) === 'string' ? msg : err.message);
        }
        alert(dmsg);
      };
    },

    async: function(proc, success, error) {
      return proc(success, error);
    },

    sync: {}

  };

<?php echo getExceptionDefs()."\n"; ?>
  Wigwam.Util            = {};

  makeApi(api);

})();
