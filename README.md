# The PHP REPL

This project contains a [read-eval-print loop](http://en.wikipedia.org/wiki/Read%E2%80%93eval%E2%80%93print_loop)(REPL) for the PHP language.

# Install

Assuming _/usr/local/lib/php_ is in your PHP include path, and _/usr/local/bin_
is in your shell path, I'd do something like this:

```bash
$ cd /usr/local/lib/php
$ git clone git://github.com/micha/wigwam.git Wigwam
$ cd Wigwam/Console
$ ln -s `pwd`/console /usr/local/bin/console
```

