RemoteCS
========

### Convenient Coding Standards Validation

RemoteCS is a remote Coding Standard Validator. Unlike regular CS Validators, it doesn't requires any changes on the client side (no IDE plugin required neither pre-commit-hook setup for GIT).
No need to set up on every developer's machine, just in one central place.

## Features:

* RemoteCS is a GitHub WebHook - Activated after each commit
* Does PHP Syntax Checking - Standard PHP Lint
* Supports PHP Codesniffer - The required plugins can be turned on / off
* Sends E-mail after each commit about the results

## Future directions:

* Send coding standard validation messages as inline comments into the commit to GitHub
* Multiple language support (Java, Ruby, Python, etc)
* Web interface
* Give points to the developers based on their code cleaniness (Weekly summary / toplist)

### References:
 
* PHP CodeSniffer: http://pear.php.net/package/PHP_CodeSniffer/

### Author

* Tamas Kalman <ktamas77@gmail.com>
