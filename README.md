# php-ssh2-sftp-client
PHP Sftp Client Class using SSH2 functions and shell commands

*By GR*

*http://www.admin.ge/portfolio*
*http://www.GR8cms.com*

*licensed under the MIT licenses

# USAGE

**Connect to an SSH server & authenticate:**

```php
$sftp = new \GR\SftpClient();

// connect
$sftp->connect($host, $port, $timeout);

// login
$sftp->login($user, $pass);
```