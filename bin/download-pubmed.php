#!/usr/bin/php -q
<?php

require dirname(__FILE__) . '/../composer/vendor/autoload.php';

$options = getopt('', array('directory:', 'config:'));

$config = new \UmnLib\Core\Config\ConfigJson(array(
  '_jsonFile' => $options['config'],
  '_requiredProperties' => array(
    'email',
    'db',
    'tool',
    'recordType',
    'searchTerms',
  ),
));

$fileSet = new \UmnLib\Core\File\Set\DateSequence(array(
  'directory' => $options['directory'],
  'suffix' => '.xml',
));

// Subtract number of seconds in a day from the current timestamp
// to get a timestamp for yesterday:
$yesterday = date('Y/m/d', time() - 86400);

$c = new  \UmnLib\Core\NcbiEUtilsClient\Client(array(
  'email' => $config->email,
  'db' => $config->db,
  'tool' => $config->tool,
  'searchTerms' => $config->searchTerms,
  'recordType' => $config->recordType,
  'startDate' => $yesterday,
  'fileSet' => $fileSet,
));
$result = $c->extract();
