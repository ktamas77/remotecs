<?php 

/**
 * GitHUB PayLoad Class Handler
 *
 * @author Tamas Kalman <ktamas77@gmail.com>
 */
Class Payload {

    var $payloadPost;
    var $payload;
    var $logDir;

    function __construct() {
        $this->payloadPost = isset($_POST['payload']) ? $_POST['payload'] : false;
        $this->payload = ($this->payloadPost) ? json_decode($this->payloadPost, true) : false;
        $this->setLogDir(__DIR__ . DIRECTORY_SEPARATOR . 'log');
    }

    public function getPayLoad() {
        return $this->payload;
    }

    public function setLogDir($logDir) {
        $this->logDir = $logDir;
    }

    /**
     * Logs the raw request
     *
     * @param String $logFile Log Filename (optional)
     *
     * @return void
     */
    public function log($logFile = null) {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir);
        }
        $logFile = $logFile ?: sprintf('post-%s-%s.log', time(), rand(100000, 999999));
        file_put_contents($this->logDir . DIRECTORY_SEPARATOR . $logFile, $this->payloadPost);
    }
}