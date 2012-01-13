<?php namespace Wigwam;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use RecursiveRegexIterator;
use FilesystemIterator;

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

  /*************************************************************************** 
   *** PRIVATE METHODS                                                     *** 
   ***************************************************************************/

  /**
   * Convenience method that returns an array containing the names of all sub-
   * directories of a given directory. Only one level of subdirectories is
   * traversed, and results are given as subdirectory name, not as a full path.
   *
   * @return array The list of subdirectories.
   */
  private static function listSubDirs($dir) {
    $ret = array();

    if ($dh = opendir($dir))
      while ( ($d = readdir($dh)) !== false)
        if (is_dir("$dir/$d") && ! preg_match('/^\\.\\.?$/', $d))
          array_push($ret, $d);

    return $ret;
  }

  /**
   * Convenience method that returns an array of all classes that can be loaded
   * from the given directory $dir, applying the given prefix $prefix to the
   * resulting list items as a prepended namespace. Subdirectories given in the
   * $prune array are not traversed (must be given as the directory name (not
   * the full path) of directories to prune which are direct children of $dir).
   *
   * @param string $dir The root directory to search.
   * @param string $prefix The namespace to prepend to each class name in the
   * result.
   * @param array $prune The list of subdirectories which are not to be tra-
   * versed when searching for class def files.
   * @return array The list of fully-qualified class names.
   */
  private static function listClassesInDir($dir, $prefix='', $prune=array()) {
    $ret = array();
    $d   = new RecursiveDirectoryIterator($dir);
    $i   = new RecursiveIteratorIterator($d);
    $r   = new RegexIterator($i, '/^[^.].*\\.php$/',
              RecursiveRegexIterator::GET_MATCH);

    foreach($r as $path) {
      $p = $path[0];
      foreach ($prune as $pr)
        if (preg_match('/^'.preg_quote("$dir/$pr/", '/').'/', $p))
          continue 2;
      array_push($ret, $p);
    }

    return array_map(function($x) use ($dir, $prefix) {
      $x = preg_replace('/^'.preg_quote("$dir/", '/').'/', "$prefix/", $x);
      $x = preg_replace('/\\//', '\\', $x);
      $x = preg_replace('/^\\\\/', '', $x);
      $x = preg_replace('/\\.php$/', '', $x);
      return $x;
    }, $ret);
  }

  /**
   * Requires the class definition file for the given fully-qualified class
   * name, if possible.
   *
   * @param string $class The fully-qualified class name.
   * @return null
   */
  private function loadWigwamClass($class) {
    if ( ($f = static::findClassDefFile($class)) )
      require_once($f);
  }

  /*************************************************************************** 
   *** PUBLIC METHODS                                                      *** 
   ***************************************************************************/

  /**
   * Constructor registers the autoload handler.
   */
  public function __construct() {
    spl_autoload_register(array($this, 'loadWigwamClass'));
  }

  /**
   * Find the class definition file for the given fully-qualified class name.
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
   * @return string The class definition file.
   */
  public static function findClassDefFile($class) {
    $root     = __DIR__;
    $relpath  = str_replace('\\', '/', $class).'.php';
    $ns       = strstr($relpath, '/', true);

    // NOTE: According to the results of extensive experimentation with pith
    // helmets, hip boots, and field notebooks, it seems that there will never
    // be a leading backslash in the $class parameter.

    if ($ns == "Wigwam") {
      if (file_exists( ($f = "$root".substr($relpath, strlen("Wigwam"))) ))
        return $f;
      elseif (count( ($vend_glob = glob("$root/vendor/*/$relpath")) ))
        return $vend_glob[0];
      return;
    }

    foreach (static::$paths as $path)
      if (file_exists("$path/$relpath"))
        return "$path/$relpath";

    foreach (static::$exact_paths as $path)
      if (basename($path)==$ns && file_exists(dirname($path)."/$relpath"))
        return dirname($path)."/$relpath";

    foreach (static::$exact_path_aliases as $alias => $path)
      if (! strncmp($relpath, "$alias/", strlen("$alias/"))
        && file_exists( ($f = "$path".substr($relpath, strlen($alias))) ))
        return $f;

  }

  /**
   * Returns array containing the fully-qualified names of all classes that can
   * be loaded by the classloader. Result is sorted.
   *
   * @return array The list of fully-qualified class names.
   */
  public static function listKnownClasses() {
    $r = array_merge(
      static::listWigwamClasses(),
      static::listPathClasses(),
      static::listExactPathClasses(),
      static::listExactPathAliasClasses()
    );
    sort($r);
    return $r;
  }

  /**
   * Returns array containing the fully-qualified names of all classes included
   * in the Wigwam package. Third-party vendor classes are not returned, as they
   * should be considered 'private' to the Wigwam package and not accessed dir-
   * ectly by client code.
   *
   * @return array The list of fully-qualified class names.
   */
  public static function listWigwamClasses() {
    return static::listClassesInDir(__DIR__, 'Wigwam', array('vendor'));
  }

  /**
   * Returns array containing the fully-qualified names of all classes loadable
   * from the paths added via addPath().
   *
   * @return array The list of fully-qualified class names.
   */
  public static function listPathClasses() {
    return array_reduce(static::$paths, function($xs, $x) {
      $xs = array_merge($xs, ClassLoader::listClassesInDir($x));
      return $xs;
    }, array());
  }

  /**
   * Returns array containing the fully-qualified names of all classes loadable
   * from the paths added via addExactPath().
   *
   * @return array The list of fully-qualified class names.
   */
  public static function listExactPathClasses() {
    return array_reduce(static::$exact_paths, function($xs, $x) {
      $dn = dirname($x);
      $bn = basename($x);
      $prune = array_diff(ClassLoader::listSubDirs($dn), array($bn));
      $xs = array_merge($xs, array_map(function($x) use ($bn) {
        return "$bn\\$x";
      }, ClassLoader::listClassesInDir($x, $prune)));
      return $xs;
    }, array());
  }

  /**
   * Returns array containing the fully-qualified names of all classes loadable
   * from the paths added via addExactPathAlias().
   *
   * @return array The list of fully-qualified class names.
   */
  public static function listExactPathAliasClasses() {
    $ret = array();

    foreach (static::$exact_path_aliases as $a => $p) {
      $dn     = dirname($p);
      $bn     = basename($p);
      $prune  = array_diff(ClassLoader::listSubDirs($dn), array($bn));
      $ret    = array_merge($ret, array_map(function($x) use ($a) {
                  return "$a\\$x";
                }, ClassLoader::listClassesInDir($p, $prune)));
    }

    return $ret;
  }

  /**
   * Add a directory to the list of directories searched when trying to find
   * a class definition file.
   *
   * @param string $path The directory.
   * @return null
   */
  public static function addPath($path) {
    $path = preg_replace('/\\/$/', '', $path);
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
    $path = preg_replace('/\\/$/', '', $path);
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
    $path = preg_replace('/\\/$/', '', $path);
    if (! array_key_exists($alias, static::$exact_path_aliases))
      static::$exact_path_aliases[$alias] = $path;
  }

}

new ClassLoader();
