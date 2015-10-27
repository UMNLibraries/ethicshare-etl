#!/usr/bin/php -q
<?php

// Environment and config data:

$options = getopt('', array('source:'));
$source = $options['source'];

$binDir = dirname(__FILE__) . '/';
$baseDir = realpath($binDir . '/../') . '/';
$configDir = $baseDir . 'config/';
$downloadsDir = $baseDir . 'downloads/';

$originalsDir = $downloadsDir . $source . '/originals';
$dedupedDir = $downloadsDir . $source . '/deduped';

$localPaths = json_decode(file_get_contents($configDir . 'local-paths.json'));

// These data sources have their own specialized download apps:

$specialDownloads = array(
  'pubmed',
  'worldcat',
);
if (in_array($source, $specialDownloads)) {
  $downloadApp = 'download-' . $source . '.php';
} else {
  $downloadApp = 'download-feed.php';
}

// Build the command lines for the apps to be run for this source:

$download = array(
  $binDir . '/' . $downloadApp,
  '--directory=' . $originalsDir,
  '--config=' . $configDir . 'download/' . $source . '.json',
);

$dedupe = array(
  $binDir . '/dedupe-citations.php',
  '--inputDirectory=' . $originalsDir,
  '--outputDirectory=' . $dedupedDir,
  '--mysqlConfig=' . $configDir . 'mysql.json',
  '--dedupeConfig=' . $configDir . 'dedupe/' . $source . '.json',
);

$drush = array(
  $localPaths->drush,
  'cite-load',
  '--root=' . $localPaths->drupal,
  '--autoloader=' . $baseDir . 'composer/vendor/autoload.php',
  '--inputDirectory=' . $dedupedDir,
  '--log=' . $baseDir . 'log/' . $source . '-error.log',
  '--configBase=' . $configDir,
  '--drupalConfig=drupal.json',
  '--mysqlConfig=mysql.json',
  '--etlConfig=etl/' . $source . '.json',
);

// Run the apps:

foreach (array($download, $dedupe, $drush) as $cmdArray) {
  $cmd = implode(' ', $cmdArray);  
  exec($cmd . ' 2>&1', $output, $return);
  if ($return) {
    $error = implode("\n", $output);
    exit("System call '$cmd' failed: $error\n");
  }
}
