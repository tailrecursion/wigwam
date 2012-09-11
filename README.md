![PHP REPL](https://raw.github.com/micha/wigwam/master/php-repl.png)

# The PHP REPL

This project contains (among other things) a [read-eval-print loop](http://en.wikipedia.org/wiki/Read%E2%80%93eval%E2%80%93print_loop) (REPL) for the PHP language.

### Features

1. Evaluates expressions in the global scope so global variables are accessible.
2. Requires no local or global variables; won't clobber anything.
3. Handles all types of errors and exceptions without exiting.
4. Can be configured via .htaccess files.
5. Has history support via readline, and in-REPL result history variables.
6. In-REPL tab completion and documentation for everything.
7. Deals correctly with PHP statements vs. expressions.
7. Optional colored output.
8. Can be extended easily.

### Caveats

The REPL was designed to work with the [Wigwam classloader](https://github.com/micha/wigwam/blob/master/ClassLoader.php). The `-p` command line option allows the inclusion of other
classloaders prior to loading the Wigwam classloader, but tab completion may
not work for those classes. Once these classes have been loaded, though, the tab
completion mechanism will know about them.

**Note:** Files included via the `-p` option do not run in the global scope.

### Dependencies

1. PHP 5.3 or better
2. Readline PHP module

### Install

Assuming _/usr/local/lib/php_ is in your PHP include path, and _/usr/local/bin_
is in your shell path, I'd do something like this:

```bash
$ cd /usr/local/lib/php
$ git clone git://github.com/micha/wigwam.git Wigwam
$ cd Wigwam
$ ln -s `pwd`/console /usr/local/bin/console
```

### Usage

```
Usage: console [OPTIONS]

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
  -v <var=val>  Set $var to "val" globally.
  -z            Run script files but don't start interactive REPL.

Multiple -f, -i, and -v options may be specified on the same command line.

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
```

# The Rest Of Wigwam

Wigwam is a PHP [remote procedure call](http://en.wikipedia.org/wiki/Remote_procedure_call)
(RPC) framework. The idea is that the serverside PHP code contains only
business logic---no HTTP, JSON, or HTML producing code required. Wigwam
exposes public static methods of specified API classes to the client.

### Features

1. API class methods are normal PHP functions. They take PHP data arguments
   and return PHP data.
2. Calling convention on the client (JavaScript client library included) is
   the same as on the server. The function `MyNS\Foo::bar(mixed $x)` could
   be called from the client JavaScript as `Wigwam.sync.MyNS.Foo.bar("asdf")`
   (for synchronous requests---async is also implemented).
3. Exceptions can be thrown in the PHP API class and caught in the client
   JavaScript environment.
4. RESTful transport.
5. Automatic content negotiation, anti-CSRF token management, etc.
6. HTTP-related configuration is via docstring annotations. For example the
   `@verb` annotation tells the server which HTTP method to use when making
   the request.
7. Authorization of HTTP requests is via pluggable modules and authorization
   is specified declaratively as docstring annotations on the API methods.
   This decouples the API function code from the HTTP-related code.

### Usage

If you really want to use Wigwam send me a message and I can show you some
live examples and help you get started. Otherwise, there is a probably out
of date example you can look at over [here](https://github.com/micha/wigwam-example).

# License

```
Copyright (c) 2012 Micha Niskin

Permission is hereby granted, free of charge, to any person obtaining a
copy of this software and associated documentation files (the "Software"),
to deal in the Software without restriction, including without limitation
the rights to use, copy, modify, merge, publish, distribute, sublicense,
and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included
in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR
OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.
```
