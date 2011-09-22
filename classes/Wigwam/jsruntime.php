<?php

function getExceptionDefs() {
  $ret = array();
  $dir = dirname(__FILE__);
  if ($handle = opendir($dir)) {
    while (false !== ($file = readdir($handle)))
      if (is_file("$dir/$file") && !preg_match('/^\\./', $file)
          && $file !== basename(__FILE__)) {
        $cls = 'Wigwam\\'.preg_replace('/\\.php$/','',$file);
        if (class_exists($cls, false) || class_exists($cls)) {
          $r = new ReflectionClass($cls);
          if ($r->isSubclassOf('Wigwam\\Exception')) {
            $p      = $r->getParentClass()->name;
            $lhs    = preg_replace('/\\\\/', '.', $cls);
            $rhs    = preg_replace('/\\\\/', '.', $p);
            $ret[]  = sprintf("  %-23s = makeErrClass(%s);", $lhs, $rhs);
          }
        }
      }
    closedir($handle);
  }
  return $ret;
}

?>
(function() {

  var api = <?php echo str_replace('\\/', '/', json_encode($api)) ?>;

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

  function makeErrClass(proto) {
    var F = function(data) { $.extend(this, data) }
    F.prototype = new proto();
    return F;
  }

  var Wigwam = {
    App: {},

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
        url:          Wigwam.cfg.base+'/json'+url,
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
        },
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

    sync: {Wigwam: {App: {}}}

  };

  Wigwam.cfg.argv        = getArgv();

  Wigwam.Exception        = makeErrClass(Error);
<?php
  echo join("\n", getExceptionDefs())."\n";
?>

  Wigwam.Util = {};

  if (api) {
    $.each(api.methods, function(i,v) {
      Wigwam.App[v.name] = function() {
        var argv=Array.prototype.slice.call(arguments), 
            data={}, ret;

        $.each(v.params, function(i,v) {
          data[v.name] = argv[i];
        });

        return function(success, error, sync) {
          return Wigwam.ajax(v.verb, v.route, data, success, error, !sync);
        };
      };
      Wigwam.sync.Wigwam.App[v.name] = function() {
        var argv=Array.prototype.slice.call(arguments), 
            ret, ex;

        Wigwam.App[v.name].apply(window, argv)(
          function(data) { ret = data },
          function(err) { ex = err },
          true
        );

        if (ex) throw ex;
        return ret;
      };
    });
  }

  window.Wigwam = Wigwam;

})();
