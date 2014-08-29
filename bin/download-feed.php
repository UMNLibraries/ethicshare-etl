#!/usr/bin/php -q
<?php

require dirname(__FILE__) . '/../composer/vendor/autoload.php';

$options = getopt('', array('directory:', 'config:'));

$config = new \UmnLib\Core\Config\ConfigJson(array(
    '_jsonFile' => $options['config'],
    '_requiredProperties' => array(
        'urls',
    ),
));

$fileSet = new \UmnLib\Core\File\Set\DateSequence(array(
    'directory' => $options['directory'],
    'suffix' => '.xml',
));

$lff = new \UmnLib\Core\LocalFeed\File($fileSet);
foreach ($config->urls as $url) {
    $lff->download( $url );
}
