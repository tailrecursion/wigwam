# Install

1. Clone this repository into a directory that's in your PHP include path.
2. In the `Console` subdirectory there is a script called `console`. Make
a symbolic link from that to a directory that is in your shell path.

### Example

Assuming _/usr/local/lib/php_ is in your PHP include path, and _/usr/local/bin_
is in your shell path, I'd do something like this:

```bash
$ cd /usr/local/lib/php
$ git clone git://github.com/micha/wigwam.git Wigwam
$ cd Wigwam
$ ln -s /usr/local/lib/php/Wigwam/Console/console /usr/local/bin/console
```

# The PHP REPL


