#!/usr/bin/php
<?php
/**
 * GitHub Payload tester
 * Outputs a Payload log file in human readable format
 *
 * @author Tamas Kalman <ktamas77@gmail.com>
 */
require_once 'Payload.class.php';
require_once '3rdparty.lib/SimpleEmailService.class.php';
require_once 'config.php';

$filename = isset($argv[1]) ? $argv[1] : null;

if (!$filename) {
    printf("Usage: %s <logfile>\n", basename(__FILE__));
    exit();
}

$payload = new Payload();
$result = $payload->loadPayloadFromLog($filename);
if ($result === false) {
    printf("Can't load logfile: [%s]\n", $filename);
    exit();
}

$payload->downloadRepository();
$problems = $payload->validateCommits();
$payload->removeSourceDir();

if ($problems !== true) {
    $payload->sendEmail($problems);
}

