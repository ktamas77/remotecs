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
     * Removes source files
     */
    protected function _removeSourceDir()
    {
        if (is_dir($this->sourceDir)) {
            exec ('rm -rf ' . $this->sourceDir);
        }
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

    public function downloadRepository()
    {
        $this->_removeSourceDir();
        exec($this->getGitCommand());
    }

    /**
     * Using PHP Lint, check the file's syntax
     *
     * @param String $filename Filename to check in source dir
     * 
     * @return boolean
     */
    protected function _checkSyntax($filename)
    {
        exec('php -l ' . $this->sourceDir . DIRECTORY_SEPARATOR . $filename, $output);
        if (isset($output[0]) && ($this->_startsWith($output[0], 'No syntax errors detected'))) {
            return true;
        }
        return $output;
    }

    protected function _validateFile($filename)
    {
        echo $filename . "\n";
        $syntax = $this->_checkSyntax($filename);
        if ($syntax !== true) {
            $problem = Array(
              'file' => $filename,
              'type' => 'syntax error',
              'description' => $syntax
            );
            return $problem;
        }
        return true;
    }

    public function validateCommits()
    {
        $commit = $this->payload['head_commit'];
        $committer = $commit['committer'];
        $mask = array('added', 'modified');
        $problems = Array();
        foreach ($mask as $m) {
            $filelist = $commit[$m];
            foreach ($filelist as $filename) {
                $result = $this->_validateFile($filename);
                if ($result !== true) {
                    $problems[] = $result;
                }
            }
        }
        if (count($problems) == 0) {
            return true;
        }
        return $problems;
    }

    private function _startsWith($haystack, $needle)
    {
        return !strncmp($haystack, $needle, strlen($needle));
    }
}