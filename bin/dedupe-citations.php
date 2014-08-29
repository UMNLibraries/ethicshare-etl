#!/usr/bin/php -q
<?php

require dirname(__FILE__) . '/../composer/vendor/autoload.php';

$options = getopt('', array(
  'inputDirectory:', // Absolute, full path to a directory of files that may contain duplicate records.
  'outputDirectory:', // Absolute, full path to a destination directory for de-duplicated files.
  'dedupeConfig:', // Absolute, full path to a de-duplication configuration file.
  'mysqlConfig:', // Absolute, full path to a MySQL configuration file.
));

$mysqlConfig = new \UmnLib\Core\Config\ConfigJson(array(
    '_jsonFile' => $options['mysqlConfig'],
    '_requiredProperties' => array(
        'host = localhost',
        'username',
        'password',
        'database',
    ),
));
$mysqli = new mysqli(
    $mysqlConfig->host,
    $mysqlConfig->username,
    $mysqlConfig->password,
    $mysqlConfig->database
);
if (mysqli_connect_errno()) {
    throw new \RuntimeException("Connect failed: " . mysqli_connect_error());
}

$dedupeConfig = new \UmnLib\Core\Config\ConfigJson(array(
    '_jsonFile' => $options['dedupeConfig'],
    '_requiredProperties' => array(
        'sources',
        'xmlRecordClass',
        'xmlRecordFileClass',
    ),
));

$externalIdSets = array();
foreach ($dedupeConfig->sources as $idType => $source) {
    $citeIdSet = new \UmnLib\EthicShare\CiteIdentifierSet(array(
        'mysqli' => $mysqli,
        'source' => $source,
    ));
    $externalIdSets[$idType] = $citeIdSet;
}

$dupesFileSet = new \UmnLib\Core\File\Set\DateSequence(array(
    'directory' => $options['inputDirectory'],
    'suffix' => '.xml',
));

$dedupedFileSet = new \UmnLib\Core\File\Set\DateSequence(array(
    'directory' => $options['outputDirectory'],
    'suffix' => '.xml',
));

$xrd = new \UmnLib\Core\XmlRecord\Deduplicator(array(
    'xmlRecordClass' => $dedupeConfig->xmlRecordClass,
    'xmlRecordFileClass' => $dedupeConfig->xmlRecordFileClass,
    'inputFileSet' => $dupesFileSet,
    'outputFileSet' => $dedupedFileSet,
    'externalIdSets' => $externalIdSets,
));

$xrd->deduplicate();
