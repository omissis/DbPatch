<?php

// Add src path is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(dirname(__FILE__) . '/../src'),
    realpath(dirname(__FILE__) . '/src'),
    get_include_path(),
)));

// set path to add the include_paths exposed by composer
$composer_include_paths = require_once dirname(__DIR__) . '/vendor/composer/include_paths.php';
foreach ($composer_include_paths as $path) {
    set_include_path($path . PATH_SEPARATOR . get_include_path());
}

require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('DbPatch_');
