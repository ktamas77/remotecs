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
    var $standard;

    function __construct()
    {
        $this->setLogDir(__DIR__ . DIRECTORY_SEPARATOR . 'log');
        $this->setSourceDir(__DIR__ . DIRECTORY_SEPARATOR . 'source-' . date('U') . '-' . rand(100000, 999999));
        $this->setCodingStandard('Zend');
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

    public function setCodingStandard($standard)
    {
        $this->standard = $standard;
    }

    public function setSourceDir($sourceDir)
    {
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
    public function removeSourceDir()
    {
        if (is_dir($this->sourceDir)) {
            exec('rm -rf ' . $this->sourceDir);
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
        exec($this->getGitCommand());
    }

    /**
     * Using PHP Lint, check the file's syntax
     *
     * @param String $filename Filename to check in source dir
     * 
     * @return Boolean|Array
     */
    protected function _checkSyntax($filename)
    {
        exec('/usr/bin/php -l ' . $this->sourceDir . DIRECTORY_SEPARATOR . $filename, $output);
        if (isset($output[0]) && ($this->_startsWith($output[0], 'No syntax errors detected'))) {
            return true;
        }
        $output = $this->_removeSourceDirFromTextArray($output);
        return $output;
    }

    /**
     * Using PHPCS, checking standards
     * 
     * @param String $filename Filename
     *
     * @return Boolean|Array
     */
    protected function _checkStandards($filename)
    {
        exec('/usr/bin/phpcs --standard=' . $this->standard . ' ' . $this->sourceDir . DIRECTORY_SEPARATOR . $filename, $output);
        if (isset($output[0]) && ($this->_startsWith($output[0], 'Time'))) {
            return true;
        }
        $output = $this->_removeSourceDirFromTextArray($output);
        return $output;
    }

    /**
     * Strips out source dir path from the given Array
     *
     * @param Array $textArray Text Array
     *
     * @return Array
     */
    protected function _removeSourceDirFromTextArray($textArray)
    {
        foreach ($textArray as &$line) {
            $line = str_replace($this->sourceDir, '', $line);
        }
        return $textArray;
    }

    public function getCommitId()
    {
        return $this->payload['head_commit']['id'];
    }

    protected function _validateFile($filename)
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        if ($ext == 'php') {
            $syntax = $this->_checkSyntax($filename);
            if ($syntax !== true) {
                $problem = Array(
                    'file' => $filename,
                    'type' => 'syntax error',
                    'description' => $syntax
                );
                return $problem;
            }

            $cs = $this->_checkStandards($filename);
            if ($cs !== true) {
                $problem = Array(
                    'file' => $filename,
                    'type' => 'coding standards validation',
                    'description' => $cs
                );
                return $problem;
            }
        }

        return true;
    }

    /**
     * Returns with the details of the committer ['name', 'email']
     *
     * @return Array
     */
    public function getCommitterDetails()
    {
        $committer = $this->payload['head_commit']['committer'];
        return $committer;
    }

    public function validateCommits()
    {
        $commit = $this->payload['head_commit'];
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

    /**
     * Sends an Email to the Committer with the details
     *
     * @param Array $problems Problems
     *
     * @return void
     */
    public function sendEmail($problems)
    {
        $committer = $this->getCommitterDetails();
        $subject = "Coding Standards information about your Commit #" . $this->getCommitId();
        $message = 'Hey ' . $committer['name'] . ",\n\n";
        $message .= "We've found the following coding standards notifications in your recent commit:\n\n";
        foreach ($problems as $problem) {
            $message .= 'File: ' . $problem['file'] . "\n";
            $message .= 'Type: ' . $problem['type'] . "\n\n";
            foreach ($problem['description'] as $line) {
                $message .= $line . "\n";
            }
            $message .= "\n";
        }
        $message .= "Sincerely,\n\nThe Coding Standards Validator Robot";

        $ses = new SimpleEmailService(SES_ACCESS, SES_SECRET);
        $mail = new SimpleEmailServiceMessage();
        $mail->addTo($committer['email']);
        $mail->setFrom(SES_EMAIL);
        $mail->setSubject($subject);
        $mail->setMessageFromString($message);
        $ses->sendEmail($mail);
    }

}