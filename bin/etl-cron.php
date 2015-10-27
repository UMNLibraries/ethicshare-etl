#!/usr/bin/php -q
<?php

require dirname(__FILE__) . '/../composer/vendor/autoload.php';

use Symfony\Component\Finder\Finder;

$binDir = dirname(__FILE__) . '/';
$cronDir = realpath($binDir . '/../cron/') . '/';

$finder = new Finder();
$apps = $finder->files()->name('*.php')->in($cronDir)->sortByName();
foreach($apps as $app) {
  $cmd = $app->getRealPath();
  exec($cmd . ' 2>&1', $output, $return);
  if ($return) {
    $error = implode("\n", $output);
    exit("System call '$cmd' failed: $error\n");
  }
}
