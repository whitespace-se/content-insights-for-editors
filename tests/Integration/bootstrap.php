<?php

$testsDirectory = getenv('WP_TESTS_DIR');
if (!$testsDirectory) {
  $testsDirectory = '/tmp/wordpress-tests-lib';
}

if (!file_exists($testsDirectory . '/includes/functions.php')) {
  fwrite(STDERR, "WordPress test suite not found. Set WP_TESTS_DIR.\n");
  exit(1);
}

require_once $testsDirectory . '/includes/functions.php';

tests_add_filter('muplugins_loaded', function () {
  if (!defined('CONTENT_INSIGHTS_FOR_EDITORS_VERSION')) {
    define('CONTENT_INSIGHTS_FOR_EDITORS_VERSION', '2.4.4-test');
  }

  require_once dirname(__DIR__, 2) . '/source/php/Vendor/Psr4ClassLoader.php';
  $loader = new CONTENT_INSIGHTS_FOR_EDITORS\Vendor\Psr4ClassLoader();
  $loader->addPrefix(
    'CONTENT_INSIGHTS_FOR_EDITORS',
    dirname(__DIR__, 2) . '/source/php/'
  );
  $loader->register();
});

require $testsDirectory . '/includes/bootstrap.php';
