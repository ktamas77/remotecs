<?php

/**
 * GitHUB PayLoad Class Handler
 *
 * Requires GIT executable
 * Passwordless SSH Key should be set up on the server
 *
 * @author Tamas Kalman <ktamas77@gmail.com>
 */
Class Payload
{

    var $payloadPost;
    var $payload;
    var $logDir;
    var $sourceDir;

    function __construct()
    {
        $this->setLogDir(__DIR__ . DIRECTORY_SEPARATOR . 'log');
        $this->setSourceDir(__DIR__ . DIRECTORY_SEPARATOR . 'source');
        $this->loadPayloadFromPost();
    }

    /**
     * Loads payload from HTTP Post
     *
     * @return void
     */
    public function loadPayloadFromPost()
    {
        $this->payloadPost = isset($_POST['payload']) ? $_POST['payload'] : false;
        $this->setPayLoad($this->payloadPost);
    }

    /**
     * Loads Payload from Logfile from the log directory
     * 
     * @param String $filename Log Filename
     */
    public function loadPayloadFromLog($filename)
    {
        $this->payloadPost = file_get_contents($this->logDir . DIRECTORY_SEPARATOR . $filename);
        $this->setPayLoad($this->payloadPost);
    }

    public function getPayLoad()
    {
        return $this->payload;
    }

    public function setSourceDir($sourceDir) {
        $this->sourceDir = $sourceDir;
    }

    public function setPayLoad($payload)
    {
        $this->payload = ($payload) ? json_decode($payload, true) : false;
    }

    public function setLogDir($logDir)
    {
        $this->logDir = $logDir;
    }

    /**
     * Logs the raw request
     *
     * @param String $logFile Log Filename (optional)
     *
     * @return void
     */
    public function log($logFile = null)
    {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir);
        }
        $logFile = $logFile ? : sprintf('post-%s-%s.log', time(), rand(100000, 999999));
        file_put_contents($this->logDir . DIRECTORY_SEPARATOR . $logFile, $this->payloadPost);
    }

    /**
     * Generates a GIT clone shell command based on the payload
     *
     * @return String $command
     */
    public function getGitCommand()
    {
        $repositoryUrl = $this->payload['repository']['url'];
        $projectPath = str_replace('https://github.com/', '', $repositoryUrl);
        $sshPath = 'git@github.com:' . $projectPath;
        $command = 'git clone ' . $sshPath . ' ' . $this->sourceDir;
        return $command;
    }

}