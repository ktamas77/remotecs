#!/usr/bin/php
<?php
/**
 * GitHub Payload tester
 * Outputs a Payload log file in human readable format
 *
 * @author Tamas Kalman <ktamas77@gmail.com>
 */
require_once 'Payload.class.php';

$filename = isset($argv[1]) ? $argv[1] : null;

if (!$filename) {
    printf("Usage: %s <logfile>\n", basename(__FILE__));
    exit();
}

$payload = new Payload();
$payload->loadPayloadFromLog($filename);

$pl = $payload->getPayLoad();

print_r($pl);

$payload->downloadRepository();
$problems = $payload->validateCommits();

print_r($problems);