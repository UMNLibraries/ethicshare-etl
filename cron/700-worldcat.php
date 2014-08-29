#!/usr/bin/php -q
<?php

$binDir = realpath(dirname(__FILE__) . '/../bin');
$basename = basename(__FILE__, '.php');
$source = preg_replace('/^\d+-/', '', $basename);

exec($binDir . '/etl-source-full-cycle.php 2>&1', $output, $return);
if ($return) {
  $error = array_pop($output);
  exit("System call '$cmd' failed: '$error'\n");
}
