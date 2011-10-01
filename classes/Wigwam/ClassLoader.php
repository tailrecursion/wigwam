<?php namespace Wigwam;

/**
 *
 * Autoload classes in the classes directory, using the namespace/subdir
 * convention. Reduces the need for redundant require() and/or require_once()
 * calls.
 * 
 *---------------------------------------------------------------------------
 *  
 * APPROOT/
 *   |
 *   +-- WIGWAM_DIR/
 *   |     |
 *   |     +-- classes/
 *   |     |     |
 *   |     |     +-- Wigwam/
 *   |     |           |
 *   |     |           +-- vendor/
 *   |     |           |     |
 *   |     |           |     +-- Slim/
 *   |     |           |           |
 *   |     |           |           +-- Slim.php
 *   |     |           |
 *   |     |           |
 *   |     |           +-- ClassLoader.php
 *   |     |           |
 *   |     |           +-- Exception.php
 *   |     |           |
 *   |     |           +-- NotAllowed.php
 *   |     |           |
 *   |     |           +-- ...
 *   |     |      
 *   |     +-- index.php
 *   |
 *   +-- classes/
 *         |
 *         +-- Test/
 *               |
 *               +-- Thinger.php
 *      
 *---------------------------------------------------------------------------
 *
 * Thinger.php:
 *
 * <?php namespace Test;
 * 
 *   class Thinger {
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
 *   // Add the application's classes dir to the classpath.
 *   Wigwam\ClassLoader::$paths[] = realpath('../classes');
 *
 *   // Autoload a class.
 *   $obj = new Test\Thinger();
 *
 *   // Autoload a wigwam class.
 *   if ($auth == false)
 *     throw new Wigwam\NotAllowed("you can't do that!");
 *
 *   // Autoload a class from a vendor (used internally in Wigwam).
 *   Slim::init();
 *
 * ?>
 *
 */

class ClassLoader {

  // Expects array of absolute paths to directories where
  // class files can be found.
  public static $paths = array();

  public function __construct() {
    spl_autoload_register(array($this, 'loadWigwamClass'));
  }

  private function loadWigwamClass($class) {
    $root       = dirname(__FILE__);
    $relpath    = str_replace('\\', '/', $class).'.php';

    $wig_path   = "$root/../$relpath";
    $vend_glob  = glob("$root/vendor/*/$relpath");

    foreach (static::$paths as $path)
      if (file_exists("$path/$relpath")) {
        require "$path/$relpath";
        return;
      }

    if (file_exists($wig_path))
      require $wig_path;
    elseif (count($vend_glob))
      require $vend_glob[0];
  }

  public static function addPath($path) {
    static::$paths[] = $path;
  }

}

new ClassLoader();
