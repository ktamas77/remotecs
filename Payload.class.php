<?php

/**
 * GitHUB PayLoad Class Handler
 *
 * Requires GIT executable
 * Passwordless SSH Key should be set up on the server
 * 
 * Make sure this script has sufficient rights to run git
 *
 * @author Tamas Kalman <ktamas77@gmail.com>
 */
Class Payload
{
    const GIT_PATH = '/usr/bin/git';
    const PHP_PATH = '/usr/bin/php';
    const PHPCS_PATH = '/usr/bin/phpcs';
    const SUDO_PATH = '/usr/bin/sudo';

    protected $_debug;
    var $payloadPost;
    var $payload;
    var $logDir;
    var $sourceDir;
    var $standard;

    /**
     * Constructor
     * 
     * @return void 
     */
    function __construct()
    {
        $this->setLogDir(__DIR__ . DIRECTORY_SEPARATOR . 'log');
        $this->setSourceDir(__DIR__ . DIRECTORY_SEPARATOR . 'source-' . date('U') . '-' . rand(100000, 999999));
        $this->setCodingStandard('Zend');
        $this->loadPayloadFromPost();
        $this->setDebug(false);
    }
    
    /**
     * Debug Functions on/off
     * 
     * @param String $debug Debug
     * 
     * @return void
     */
    public function setDebug($debug)
    {
        $this->_debug = $debug;
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
     * 
     * @return Boolean Result
     */
    public function loadPayloadFromLog($filename)
    {
        $logFilePath = $this->logDir . DIRECTORY_SEPARATOR . $filename;
        if (is_file($logFilePath)) {
            $this->payloadPost = file_get_contents($logFilePath);
            $this->setPayLoad($this->payloadPost);
            return true;
        }
        return false;
    }

    /**
     * Get Parsed Payload
     * 
     * @return Array Payload 
     */
    public function getPayLoad()
    {
        return $this->payload;
    }

    /**
     * Sets Coding Standard for PHPCS
     * 
     * @param String $standard Standard
     * 
     * @return void 
     */
    public function setCodingStandard($standard)
    {
        $this->standard = $standard;
    }

    /**
     * Sets source dir to download GIT repository
     *
     * @param String $sourceDir Source Dir
     * 
     * @return void
     */
    public function setSourceDir($sourceDir)
    {
        $this->sourceDir = $sourceDir;
    }

    /**
     * Decodes & Sets Payload from String
     * 
     * @param String $payload Github API Payload post string
     * 
     * @return void
     */
    public function setPayLoad($payload)
    {
        $this->payload = ($payload) ? json_decode($payload, true) : false;
    }

    /**
     * Sets Log Dir
     * 
     * @param String $logDir Log Dir
     * 
     * @return void
     */
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
     * Requires SUDO
     * 
     * @return void
     */
    public function removeSourceDir()
    {
        if (is_dir($this->sourceDir)) {
            exec($this::SUDO_PATH . ' rm -rf ' . $this->sourceDir);
        }
    }

    /**
     * Generates a GIT clone shell command based on the payload
     * Requires SUDO
     *
     * @return String $command
     */
    public function getGitCommand()
    {
        $repositoryUrl = $this->payload['repository']['url'];
        $sshPath = str_replace('https://github.com/', 'git@github.com:', $repositoryUrl);
        $command = $this::SUDO_PATH . ' ' . $this::GIT_PATH . ' clone ' . $sshPath . ' ' . $this->sourceDir;
        return $command;
    }

    /**
     * Downloads Repository
     * 
     * @return void 
     */
    public function downloadRepository()
    {
        $cmd = $this->getGitCommand();
        $output = shell_exec($cmd);
        $this->debugLog($cmd);
        $this->debugLog($output);
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
        $cmd = $this::PHP_PATH . ' -l ' . $this->sourceDir . DIRECTORY_SEPARATOR . $filename;
        exec($cmd, $output);
        $this->debugLog($cmd);
        $this->debugLog($output);
        if (empty($output)) {
            return true;
        }
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
        $cmd = $this::PHPCS_PATH . ' --standard=' . $this->standard . ' ' . $this->sourceDir . DIRECTORY_SEPARATOR . $filename;
        exec($cmd, $output);
        $this->debugLog($cmd);
        $this->debugLog($output);
        if (isset($output[0]) && ($this->_startsWith($output[0], 'Time'))) {
            return true;
        }
        $output = $this->_removeSourceDirFromTextArray($output);
        return $output;
    }

    /**
     * Debug logging (if enabled)
     * 
     * @param Mixed  $var    Variable to log
     * @param String $prefix Prefix for Debug log filename
     * 
     * @return void
     */
    public function debugLog($var, $prefix = '')
    {
        if ($this->_debug) {
            file_put_contents($this->logDir . DIRECTORY_SEPARATOR . $prefix . 'debug.log', print_r($var, true)."\n\n\n", FILE_APPEND);
        }
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

    /**
     * Last Git Commit SHA from Payload
     * 
     * @return String Git Commit SHA 
     */
    public function getCommitId()
    {
        return $this->payload['head_commit']['id'];
    }

    /**
     * Validates a file against syntax and Coding Standards
     * Currently only PHP is supported
     * 
     * @param String $filename Filename
     * 
     * @return Boolean|String True or an Array containing the problem
     */
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

    /**
     * Validates all commits from head
     * 
     * @return Boolean|Array True or an Array of Problems
     */
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

    /**
     * Compares a substring with a string from the beginning
     * 
     * @param String $haystack Haystack
     * @param String $needle   Needle to search
     * 
     * @return Boolean Result
     */
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