#!/usr/bin/php -q
<?php

$binDir = realpath(dirname(__FILE__) . '/../bin');
$basename = basename(__FILE__, '.php');
$source = preg_replace('/^\d+-/', '', $basename);

exec($binDir . '/etl-source-full-cycle.php --source=' . $source . ' 2>&1', $output, $return);
if ($return) {
  $error = implode("\n", $output);
  exit("System call '$cmd' failed: $error\n");
}
