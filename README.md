# php-ssh2-sftp-client
PHP Sftp Client Class using SSH2 functions and shell commands

*By GR admin@admin.ge*

*http://www.admin.ge/portfolio*<br>
*http://www.GR8cms.com*

*Copyright (c) 2015 GR*<br>
*licensed under the MIT licenses*\n
*http://www.opensource.org/licenses/mit-license.html*

##USAGE

**Connect to an SSH server & authenticate:**

```php
$sftp = new \GR\SftpClient();

// connect
$sftp->connect($host, $port, $timeout);

// login
$sftp->login($user, $pass);
```