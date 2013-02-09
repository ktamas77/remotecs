#!/usr/bin/php
<?php
/**
 * GitHub Payload tester
 * Outputs a Payload log file in human readable format
 *
 * @author Tamas Kalman <ktamas77@gmail.com>
 */
require_once 'Payload.class.php';
require_once '3rdparty.lib/ses.php';

$filename = isset($argv[1]) ? $argv[1] : null;

if (!$filename) {
    printf("Usage: %s <logfile>\n", basename(__FILE__));
    exit();
}

$payload = new Payload();
$payload->loadPayloadFromLog($filename);
$pl = $payload->getPayLoad();
$payload->downloadRepository();
$problems = $payload->validateCommits();
$payload->removeSourceDir();

print_r($problems);
print_r($payload->getCommitterDetails());