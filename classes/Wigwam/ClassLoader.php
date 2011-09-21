<?php namespace Wigwam;

/**
 *
 * Autoload classes in the classes directory, using the namespace/subdir
 * convention. Reduces the need for redundant require() and/or require_once()
 * calls.
 * 
 *---------------------------------------------------------------------------
 *  
 * ROOT/
 *   |
 *   +-- classes/
 *   |     |
 *   |     +-- Wigwam/
 *   |     |     |
 *   |     |     +-- vendor/
 *   |     |     |     |
 *   |     |     |     +-- Slim/
 *   |     |     |           |
 *   |     |     |           +-- Slim.php
 *   |     |     |
 *   |     |     |
 *   |     |     +-- ClassLoader.php
 *   |     |
 *   |     +-- Foo/
 *   |           |
 *   |           +-- Bar/
 *   |                 |
 *   |                 +-- MyClass.php
 *   +-- index.php
 *      
 *---------------------------------------------------------------------------
 *
 * MyClass.php:
 *
 * <?php namespace Foo\Bar;
 * 
 *   class MyClass {
 *     ...
 *   }
 *
 * ?>
 *
 *---------------------------------------------------------------------------
 * 
 * index.php:
 *
 * <?php
 *
 *   // Load the classloader itself (do this first).
 *   require_once('classes/Wigwam/ClassLoader.php');
 *
 *   // Instantiate the classloader instance.
 *   new Wigwam\ClassLoader();
 *
 *   // Autoload a class.
 *   $obj = new Foo\Bar\MyClass();
 *
 *   // Autoload a class from a vendor (used internally in Wigwam).
 *   Slim::init();
 *
 * ?>
 *
 */

class ClassLoader {

  public function __construct() {
    spl_autoload_register(array($this, 'loadWigwamClass'));
  }

  private function loadWigwamClass($class) {
    $root       = dirname(__FILE__);
    $relpath    = str_replace('\\', '/', $class).'.php';

    $app_path   = "$root/../$relpath";
    $vend_glob  = glob("$root/vendor/*/$relpath");

    if (file_exists($app_path))
      require $app_path;
    elseif (count($vend_glob))
      require $vend_glob[0];
  }

}

new ClassLoader();
