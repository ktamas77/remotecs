<?php

/**
 * RemoteCS - Convenient Remote Coding Standards Validation
 *
 * @author Tamas Kalman <ktamas77@gmail.com>
 */
require_once 'Payload.class.php';
require_once '3rdparty.lib/ses.php';

$payload = new Payload();
$payload->log();