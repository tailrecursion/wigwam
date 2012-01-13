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
  public static $paths              = array();
  public static $exact_paths        = array();
  public static $exact_path_aliases = array();

  /**
   * Constructor registers the autoload handler.
   */
  public function __construct() {
    spl_autoload_register(array($this, 'loadWigwamClass'));
  }

  /**
   * Find the class definition file for the given class and require_once() it.
   *
   * Searches the file system five ways:
   *   
   * 1. Looks in the Wigwam class directory structure for a matching class def-
   *    inition file.
   *
   * 2. Looks in the Wigwam/vendor/* class directory structure for a matching
   *    class definition file.
   *
   * 3. Looks in each of the paths added by addPath(), using that directory as
   *    a root, with the fully-qualified class name as a path relative to that
   *    root.
   *
   * 4. Looks in each of the paths added by addExactPath(), using the parent
   *    directory as the root, and restricting the search to the specific sub-
   *    directory provided to addExactPath().
   *
   * 5. Looks in each of the paths added by addExactPathAlias(), using the
   *    parent directory as the root and restricting to the specific subdirect-
   *    ory as in #2, but prepends the alias provided to addExactPathAlias() to
   *    the relative path of the file when testing for a match.
   *
   * @param string $class The fully-qualified class name.
   * @return null
   */
  private function loadWigwamClass($class) {
    $root     = __DIR__;
    $relpath  = str_replace('\\', '/', $class).'.php';
    $ns       = strstr($relpath, '/', true);

    // NOTE: According to the results of extensive experimentation with pith
    // helmets, hip boots, and field notebooks, it seems that there will never
    // be a leading backslash in the $class parameter.

    if ($ns == "Wigwam") {
      if (file_exists( ($f = "$root".substr($relpath, strlen("Wigwam"))) ))
        require_once($f);
      elseif (count( ($vend_glob = glob("$root/vendor/*/$relpath")) ))
        require_once($vend_glob[0]);
      return;
    }

    foreach (static::$paths as $path)
      if (file_exists("$path/$relpath")) {
        require_once("$path/$relpath");
        return;
      }

    foreach (static::$exact_paths as $path)
      if (basename($path)==$ns && file_exists(dirname($path)."/$relpath")) {
        require_once(dirname($path)."/$relpath");
        return;
      }

    foreach (static::$exact_path_aliases as $alias => $path)
      if (! strncmp($relpath, "$alias/", strlen("$alias/"))
        && file_exists( ($f = "$path".substr($relpath, strlen($alias))) )) {
        require_once($f);
        return;
      }

  }

  /**
   * Add a directory to the list of directories searched when trying to find
   * a class definition file.
   *
   * @param string $path The directory.
   * @return null
   */
  public static function addPath($path) {
    if (! in_array($path, static::$paths))
      static::$paths[] = $path;
  }

  /**
   * Add a directory to the list of directories searched when trying to find
   * a class definition file. However, rather than adding this directory as
   * a root under which every subdirectory and source file will be searched
   * and possibly require()'d, only the specific directory given will be
   * searched.
   *
   *   /usr/
   *     |
   *     +-- local/
   *           |
   *           +-- Foo/
   *           |     | 
   *           |     +-- Bar/
   *           |     |     |
   *           |     |     +-- Baz.php
   *           |     |
   *           |     +-- Boop.php
   *           |
   *           +-- Baf.php
   *
   *
   *   ClassLoader::addExactPath('/usr/local/Foo');
   *
   *   $baf   = new \Baf();           // Not found
   *   $baz   = new \Foo\Bar\Baz();   // OK
   *   $boop  = new \Foo\Boop();      // OK
   *
   * @param string $path The directory.
   * @return null
   */
  public static function addExactPath($path) {
    if (! in_array($path, static::$exact_paths))
      static::$exact_paths[] = $path;
  }

  /**
   * Add a directory to the list of directories searched when trying to find
   * a class definition file. However, rather than adding this directory as
   * a root under which every subdirectory and source file will be searched
   * and possibly require()'d, only the specific directory given will be
   * searched. Furthermore, the given alias will be prepended to the relative
   * path of the php file when searching for a matching file for the class.
   *
   *   /usr/
   *     |
   *     +-- local/
   *           |
   *           +-- Foo/
   *           |     | 
   *           |     +-- Bar/
   *           |     |     |
   *           |     |     +-- Baz.php
   *           |     |
   *           |     +-- Boop.php
   *           |
   *           +-- Baf.php
   *
   *
   *   ClassLoader::addExactPath('/usr/local/Foo', 'A/B');
   *
   *   $baz   = new \Foo\Bar\Baz();   // Not found
   *   $baf   = new \Baf();           // Not found
   *   $boop  = new \A\B\Boop();      // OK
   *   $boop  = new \A\B\Bar\Baz();   // OK
   *
   * @param string $path The directory.
   * @return null
   */
  public static function addExactPathAlias($path, $alias) {
    if (! array_key_exists($alias, static::$exact_path_aliases))
      static::$exact_path_aliases[$alias] = $path;
  }

}

new ClassLoader();
