#!/usr/bin/php -q
<?php

$configDir = realpath(dirname(__FILE__) . '/../config');
$localPaths = json_decode(file_get_contents($configDir . '/local-paths.json'));

exec($localPaths->drush . ' cron --root=' . $localPaths->drupal . ' 2>&1', $output, $return);
if ($return) {
  $error = array_pop($output);
  exit("System call '$cmd' failed: '$error'\n");
}
