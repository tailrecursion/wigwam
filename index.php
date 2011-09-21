<?php

use Wigwam\HTTP;
use Wigwam\Test\App;

//===========================================================================//
// CLASSLOADER INITIALIZATION                                                //
//===========================================================================//

require 'classes/Wigwam/ClassLoader.php';

//===========================================================================//
// CONFIGURE APPLICATION/HTTP WRAPPER AND HANDLE REQUEST                     //
//===========================================================================//

/**
 * Main function to encapsulate any local variables.
 */
function main() {
  // Config files are processed in order. Settings in files processed later
  // will overwrite settings from files processed earlier.
  $config_files = array('config/config.yaml', 'config/config.local.yaml');

  // Create new HTTP wrapper instance.
  $http = new HTTP($config_files);

  // Load the application API into the HTTP wrapper.
  $http->addApi(new App());

  // Add a specific route handler separate from the API.
  $http->get('/foo', function() use ($http) {
    return $http->render(array('foo', 'bar', 'baz'));
  });

  // Delegate to the HTTP wrapper.
  $http->run();
}

// Doit.
main();
