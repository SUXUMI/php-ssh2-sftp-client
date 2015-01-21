# php-ssh2-sftp-client
PHP Sftp Client Class using SSH2 functions and shell commands

*By GR admin@admin.ge*

*[Portfolio]http://www.admin.ge/portfolio*<br>
*[GR8cms.com]http://www.GR8cms.com*

*Copyright (c) 2015 GR*<br>
*licensed under the MIT licenses*<br>
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

**Get current directory:**
```php
$sftp->getCurrentDirectory();
```

#####Create directory
```php
$sftp->createDirectory($path, $ignore_if_exists);
```

#####Delete directory
```php
$sftp->deleteDirectory($path);
```

#####Delete file
```php
$sftp->deleteFile($path);
```

#####Get directory content list
```php
// just names
$sftp->getDirectoryList($path, $recursive);

// rawlist
$sftp->getDirectoryRawList($path, $recursive);

// <b>formated</b> rawlist
$sftp->getDirectoryRawListFormatted($path, $recursive);
```

#####Get file stat
```php
$stat = $sftp->stat($path); 
```






