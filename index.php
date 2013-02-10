<?php

/**
 * RemoteCS - Convenient Remote Coding Standards Validation
 * This file should be called via HTTP from GitHub as a Webhook
 * Make sure the script has rights to run Git
 *
 * @author Tamas Kalman <ktamas77@gmail.com>
 */
require_once 'Payload.class.php';
require_once '3rdparty.lib/SimpleEmailService.class.php';
require_once 'config.php';

$payload = new Payload();
$payload->log();
$result = $payload->downloadRepository();
if (!$result) {
    $payload->debugLog('Cannot run Git');
    exit();
}
$problems = $payload->validateCommits();
$payload->removeSourceDir();

if ($problems !== true) {
    $payload->sendEmail($problems);
    $payload->debugLog($problems, 'problems-');
}
