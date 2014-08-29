#!/usr/bin/php -q
<?php

require dirname(__FILE__) . '/../composer/vendor/autoload.php';

$options = getopt('', array(
  'config:', // Full path and filename for a configuration file.
  'directory:', // Full path to a destination directory for downloaded files.
  'dateRange::', // Date range (year1-year2) within which to restrict the WorldCat search results.
));

$config = new \UmnLib\Core\Config\ConfigJson(array(
    '_jsonFile' => $options['config'],
    '_requiredProperties' => array(
        'api',
        'wskey',
        'query',
    ),
));

$fileSet = new \UmnLib\Core\File\Set\DateSequence(array(
    'directory' => $options['directory'],
    'suffix' => '.xml',
));

// Add the date range to the WorldCat search query.
if (array_key_exists('dateRange', $options)) {
    $dateRange = $options['dateRange'];
} else {
    $thisYear = date('Y', time());
    $lastYear = $thisYear - 1;
    $dateRange = "$lastYear-$thisYear";
}
$query = $config->query . $dateRange;

$ri = new \UmnLib\Core\WorldCatSearch\RequestIterator(array(
    'wskey' => $config->wskey,
    'query' => $query,
    'api'   => $config->api,
));

$c = new \UmnLib\Core\WorldCatSearch\Client(array(
    'requestIterator' => $ri,
    'fileSet' => $fileSet,
));

$c->search();
