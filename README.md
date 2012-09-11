# The PHP REPL

This project contains (among other things) a [read-eval-print loop](http://en.wikipedia.org/wiki/Read%E2%80%93eval%E2%80%93print_loop) (REPL) for the PHP language.

### Features

1. Executes expressions in the global scope so global variables are accessible.
2. Requires no local or global variables, so there is no chance that it will
   clobber anything.
3. Handles all types of errors and exceptions: the REPL does not die on fatal
   errors, for example.
4. Can be configured via .htaccess files.
5. History support via readline.
6. Tab completion for everything.
7. Optional colored output.
8. Can be extended easily.

### Caveats

The REPL was designed to work with the [Wigwam classloader](https://github.com/micha/wigwam/blob/master/ClassLoader.php). The `-p` command line option allows the inclusion of other
classloaders prior to loading the Wigwam classloader, but tab-completion may
not work for those classes. Once the class has been loaded, however, tab
completion should work fine.

**Note:** Files included via the `-p` option do not run in the global scope.

### Install

Assuming _/usr/local/lib/php_ is in your PHP include path, and _/usr/local/bin_
is in your shell path, I'd do something like this:

```bash
$ cd /usr/local/lib/php
$ git clone git://github.com/micha/wigwam.git Wigwam
$ cd Wigwam/Console
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
