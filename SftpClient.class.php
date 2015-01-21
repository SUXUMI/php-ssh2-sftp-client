<?php

/**
 * SFTP/ssh2 Client for PHP
 * 
 * PHP version 5.3 or higher
 * 
 * THE SOFTWARE IS PROVIDED "AS IS" ..
 *
 * @author     GR <admin@admin.ge>
 * @copyright  (c) GR 2015
 * @license    http://www.opensource.org/licenses/mit-license.html (The MIT License)
 * @link       http://www.admin.ge/
 * @link       http://www.GR8cms.com/
 * @version    v1.0
 */

namespace GR;

use \ErrorException;

class SftpClient {
	/**
	 * Current connection
	 * @var resource
	 */
	private $connection;
	
	/**
	 * Host
	 * @var string
	 */
	private $host;
	
	/**
	 * Port
	 * @var number
	 */
	private $port				= 22;
	
	/**
	 * Remote connection userame
	 * @var
	 */
	private $user;
	
	/**
	 * Remote connection password
	 * @var string
	 */
	private $pass;
	
	/**
	 * Remote connection timeout
	 * @var number
	 */
	private $timeout			= 90;
	
	/**
	 * SFTP subsystem
	 * @var resource
	 */
	private $sftp;
	
	/**
	 * SSH2 stream error message
	 * @var string
	 */
	private $sshErrMsg			= '';
	
	/**
	 * Remote OS
	 * @var string
	 */
	private $OS_TYPE			= 'NIX'; // WIN, NIX
	
	/**
	 * File system entry types
	 * @var array
	 */
	private $types				= array('d' => 'dir', '-' => 'file', 'l' => 'link');
	
	/**
	 * Escape chars (DO NOT CHANGE THE ORDER!!!)
	 * @var array
	 */
	private $escapeChars		= array('\\', '"', '`');

	/**
	 * LANG Constants
	 */
	const ERR_LIBSSH2						= 'libssh2 does not exists';
	const ERR_OS_TYPE						= 'Remote OS Type is not defined';
	const ERR_LOCAL_FILE_IS_NOT_WRITABLE	= 'The local file does not exists or is not writable';
	const ERR_DOWNLOAD_FAILED				= 'Unable to download file';
	const ERR_UPLOAD_FAILED					= 'Unable to upload file';
	const ERR_GET_STAT_FAILED				= 'Unable to get stat';
	
	/**
	 * Constructor
	 * 
	 * @throws ErrorException
	 */
	public function __construct() {
		if (!extension_loaded('ssh2')) {
			throw new ErrorException(self::ERR_LIBSSH2);
		}
	}
	
	/**
	 * Connect
	 * 
	 * @param string $host
	 * @param number $port
	 * @param number $timeout
	 * @throws \ErrorException
	 * @return \GR\SftpClient
	 */
	public function connect($host, $port = 22, $timeout = 90) {
		$this->host		= $host;
		$this->port		= $port;
		$this->timeout	= $timeout;
		
		if (!$this->connection = ssh2_connect($this->host, $this->port)) {
			$error = error_get_last();
			throw new \ErrorException($error['message'], 0, 1, __FILE__, __LINE__);
		}
		
		return $this;
	}
	
	/**
	 * Remote login
	 * 
	 * @param string $user
	 * @param string $pass
	 * @throws \ErrorException
	 * @return \GR\SftpClient
	 */
	public function login($user, $pass) {
		$this->user = $user;
		$this->pass = $pass;
		
		if (!@ssh2_auth_password($this->connection, $this->user, $this->pass)) {
			$error = error_get_last();
			throw new \ErrorException($error['message'], 0, 1, __FILE__, __LINE__);
		}
		else {
			$this->sftp = @ssh2_sftp($this->connection);

			if (!$this->sftp){
				$error = error_get_last();
				throw new \ErrorException($error['message'], 0, 1, __FILE__, __LINE__);
			}
		}
		
		// define remote OS Type
		$this->defineRemoteOSType();
		
		return $this;
	}
	
	/**
	 * Defines remote OS Type
	 * 
	 * @throws \ErrorException
	 */
	private function defineRemoteOSType() {
		$uname = $this->ssh2_exec('uname');
		
		if ($uname === false) {
			throw new \ErrorException($this->sshErrMsg, 0, 1, __FILE__, __LINE__);
		}
		
		if (strtolower($uname) != 'windows') {
			$this->OS_TYPE = 'NIX';
		}
	}
	
	/**
	 * Defines remote OS Type
	 */
	private function defineRemoteOSType2() {
		$rawlist = $this->getDirectoryRawList('.', false);
		
		if ($rawlist) {
			$firstFileData = preg_split("/[\s]+/", $rawlist[1], 9);
			
			// for windows the first one is a date
			// for linux - it's permissions string
			if (strlen($firstFileData[0]) == 8 && !preg_match("/[a-z]/i", $firstFileData[0]))
				$this->OS_TYPE = 'WIN';
		}
	}
	
	/**
	 * Returns current directory
	 * 
	 * @throws \ErrorException
	 * @return string
	 */
	public function getCurrentDirectory() {
		$pwd = $this->ssh2_exec('pwd');
		
		if ($pwd === false) {
			throw new \ErrorException($this->sshErrMsg, 0, 1, __FILE__, __LINE__);
		}
		
		return $pwd;
	}
	
	/**
	 * Creates remote directory
	 * 
	 * @param string $path - full path
	 * @param boolean $ignore_if_exists
	 * @throws \ErrorException
	 * @return \GR\SftpClient
	 */
	public function createDirectory($path, $ignore_if_exists = true) {
		// on failure it does not returns any error message
		// $result = ssh2_sftp_mkdir($this->sftp, $path);
		
		$result = $this->ssh2_exec('mkdir '. $this->escapePath($path));
		
		if ($result === false && !($ignore_if_exists && preg_match('/:[^:]*exists.*/i', $this->sshErrMsg)) ) {
			throw new \ErrorException($this->sshErrMsg, 0, 1, __FILE__, __LINE__);
		}
		
		return $this;
	}
	
	/**
	 * Deletes file
	 *
	 * @param string $path - full path
	 * @return S_FTPClient
	 */
	public function deleteFile($path) {
		// Removes a directory
		// $result = ssh2_sftp_rmdir($this->sftp, $source);
	
		// Deletes a file
		// $result = ssh2_sftp_unlink($this->sftp, $source);
	
		$result = $this->ssh2_exec('rm -fr '. $this->escapePath($path));
	
		if ($result === false) {
			throw new \ErrorException($this->sshErrMsg, 0, 1, __FILE__, __LINE__);
		}
	
		return $this;
	}
	
	/**
	 * Deletes directory
	 * 
	 * @param string $path - full path
	 * @throws \ErrorException
	 * @return \GR\SftpClient
	 */
	public function deleteDirectory($path) {
		return $this->deleteFile($path);
	}
	
	/**
	 * Gets directory files/folders list
	 * 
	 * @param string $path - full path
	 * @throws \ErrorException
	 * @return array
	 */
	public function getDirectoryList($path, $recursive = false) {
		$result = $this->ssh2_exec('ls -a' . ($recursive ? 'R' : '') .' '. $this->escapePath($path));
		
		if ($result === false) {
			throw new \ErrorException($this->sshErrMsg, 0, 1, __FILE__, __LINE__);
		}
		
		return explode("\n", $result);
	}
	
	/**
	 * Gets directory rawlist
	 * 
	 * @param unknown $path
	 * @param string $recursive
	 * @throws \ErrorException
	 * @return multitype:
	 */
	public function getDirectoryRawList($path, $recursive = false) {
		$result = $this->ssh2_exec('ls -la' . ($recursive ? 'R' : '') .' '. $this->escapePath($path));
		
		if ($result === false) {
			throw new \ErrorException($this->sshErrMsg, 0, 1, __FILE__, __LINE__);
		}
		
		return explode("\n", $result);
	}
	
	/**
	 * Returns data list of files
	 * 
	 * @param string $directory
	 * @param string $recursive
	 * @param array $ignoreNames
	 * @return multitype:Ambigous <multitype:, multitype:, boolean, multitype:string, string>
	 */
	public function getDirectoryRawListFormatted($path, $recursive = true, array $ignoreFilenames = array('', '.', '..')) {
		$output = array();
		
		$this->getDirectoryRawListFormattedRecursive($output, $path, $recursive, $ignoreFilenames);
		
		return $output;
	}
	
	/**
	 * Returns data list of files
	 * 
	 * @param array $output
	 * @param string $path
	 * @param boolean $recursive
	 * @param array $ignoreNames
	 */
	private function getDirectoryRawListFormattedRecursive(array &$output, $path, $recursive, array $ignoreFilenames) {
		$rawfiles = $this->getDirectoryRawList($path);
		
		foreach ((array)$rawfiles as $rawfile) {
			if (empty($rawfile)) continue;
			
			$content = $this->getRawfileFormatted($rawfile, $path, $ignoreFilenames);
			
			if (!$content) continue;
			
			$output[] = $content;
				
			if ($recursive && $content['typeDescriptor'] == 'd') {
				$this->getDirectoryRawListFormattedRecursive($output, $content['path'], $recursive, $ignoreFilenames);
			}
		}
	}
	
	/**
	 * Returns formatted data
	 * 
	 * @param string $rawfile
	 * @param string $path
	 * @param array $ignoreFilenames
	 * @return array
	 */
	private function getRawfileFormatted($rawfile, $path, array $ignoreFilenames) {
		switch ($this->OS_TYPE) {
			case 'WIN': return $this->getRawfileFormatted_WIN($rawfile, $path, $ignoreFilenames);
			default: return $this->getRawfileFormatted_NIX($rawfile, $path, $ignoreFilenames);
		}
	}
	
	/**
	 * Returns file specific data
	 * 
	 * @param string $rawfile
	 * @param string $directory
	 * @param array $ignoreNames
	 * @param array $ignorePaths
	 * @return array
	 */
	private function getRawfileFormatted_NIX($rawfile, $path, array $ignoreFilenames) {
		$out			= array();
		
		$info			= preg_split("/[\s]+/", $rawfile, 9);
		
		$month			= $info[5];
		$day			= $info[6];
		$year			= preg_match('/^\d+$/', $info[7]) ? $info[7] : date('Y');
		$time			= preg_match('/^\d+$/', $info[7]) ? ''       : $info[7];
		
		$typeDescriptor = $info[0]{0};
		
		$name			= '';
		$symlinkPath	= '';
		list($name, $symlinkPath) = explode('->', $info[8]);
		
		$name			= trim($name);
		$path			= $typeDescriptor == 'l' ? trim($symlinkPath) : $path . '/' .$name;
		
		$out = array(
				'name'			=> $name,
				'path'			=> $path,
				'type'			=> $this->types[ $typeDescriptor ],
				'typeDescriptor'=> $typeDescriptor,
				'size'			=> $info[4],
				'chmod'			=> substr($info[0], 1),
				'date'			=> strtotime($day .' '. $month .' '. $year .' '. $time),
		);
		
		if (in_array($out['name'], $ignoreFilenames))
			return false;
		
		return $out;
	}
	
	// @todo
	/**
	 * Returns file specific data (for WINDOWS)
	 * 
	 * @param string $rawfile
	 * @param string $directory
	 * @param array $ignoreNames
	 * @param array $ignorePaths
	 * @return array
	 */
	private function getRawfileFormatted_WIN($rawfile, $directory, array $ignoreNames) {
		$out = array();
		
		return $out;
	}
	
	/**
	 * Gets file stat
	 *
	 * @param string $path (full path)
	 * @throws \ErrorException
	 * @return array
	 */
	public function stat($path) {
		$result = @ssh2_sftp_stat($this->sftp, $path);
	
		if ($result === false) {
			throw new \ErrorException(self::ERR_GET_STAT_FAILED, 0, 1, __FILE__, __LINE__);
		}
	
		return $result;
	}
	
	/**
	 * Downloads a file
	 * 
	 * @param string $remote_file
	 * @param string $local_file
	 * @throws \ErrorException
	 * @return \GR\SftpClient
	 */
	public function downloadFile($remote_file, $local_file) {
		// if file not exists - try to create it
		if (!file_exists($local_file)) {
			$handle = @fopen($local_file, 'w');
			@fclose($handle);
		}
		
		if (!is_writable($local_file)) {
			throw new \ErrorException(self::ERR_LOCAL_FILE_IS_NOT_WRITABLE, 0, 1, __FILE__, __LINE__);
		}
		
		$result = @ssh2_scp_recv($this->connection, $remote_file, $local_file);
		
		if ($result === false) {
			throw new \ErrorException(self::ERR_DOWNLOAD_FAILED, 0, 1, __FILE__, __LINE__);
		}
		
		return $this;
	}
	
	/**
	 * Uploads a file
	 * 
	 * @param string $local_file
	 * @param string $remote_file
	 * @param number $create_mode
	 * @throws \ErrorException
	 * @return \GR\SftpClient
	 */
	public function uploadFile($local_file, $remote_file, $create_mode = 0644) {
		$result = @ssh2_scp_send($this->connection, $local_file, $remote_file, $create_mode);
		
		if ($result === false) {
			throw new \ErrorException(self::ERR_UPLOAD_FAILED, 0, 1, __FILE__, __LINE__);
		}
		
		return $this;
	}
	
	/**
	 * Renames a file or directory
	 * 
	 * @param string $oldname (full path)
	 * @param string $newname (full path)
	 * @throws \ErrorException
	 * @return \GR\SftpClient
	 */
	public function renameFileOrFolder($oldname, $newname) {
		// on failure it does not returns any error message
		// $result = ssh2_sftp_rename($this->sftp, $oldname, $newname);
		
		$result = $this->ssh2_exec('mv -T '. $this->escapePath($oldname) .' '. $this->escapePath($newname) .'');
		
		if ($result === false) {
			throw new \ErrorException($this->sshErrMsg, 0, 1, __FILE__, __LINE__);
		}
		
		return $this;
	}
	
	/**
	 * Renames a directory (Alias)
	 *
	 * @param string $oldname (full path)
	 * @param string $newname (full path)
	 * @throws \ErrorException
	 * @return \GR\SftpClient
	 */
	public function renameDirectory($oldname, $newname) {
		return $this->renameFileOrFolder($oldname, $newname);
	}
	
	/**
	 * Renames a directory (Alias)
	 *
	 * @param string $oldname (full path)
	 * @param string $newname (full path)
	 * @throws \ErrorException
	 * @return \GR\SftpClient
	 */
	public function renameFile($oldname, $newname) {
		return $this->renameFileOrFolder($oldname, $newname);
	}

	/**
	 * Creates a symlink
	 *
	 * @param string $target (full path)
	 * @param string $link (full path)
	 * @throws \ErrorException
	 * @return \GR\SftpClient
	 */
	public function createSymlink($target, $link) {
		// $result = ssh2_sftp_symlink($this->sftp, $target, $name);
	
		$result = $this->ssh2_exec('ln -s '. $this->escapePath($target) .' '. $this->escapePath($link) .'');
	
		if ($result === false) {
			throw new \ErrorException($this->sshErrMsg, 0, 1, __FILE__, __LINE__);
		}
	
		return $this;
	}
	
	/**
	 * Run shell script
	 * 
	 * @param string $cmd
	 * @param string $trimOutput
	 * @return Ambigous: <boolean, empty, string> (FALSE on error)
	 */
	private function ssh2_exec($cmd, $trimOutput = true) {
		$stream  = ssh2_exec($this->connection, $cmd);
		stream_set_blocking($stream, true);
		
		$content = stream_get_contents($stream);
		$content = $trimOutput ? trim($content) : $content;
		
		$stderr_stream   = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
		$content_error   = stream_get_contents($stderr_stream);
		$this->sshErrMsg = $content_error;
		
		return strlen($this->sshErrMsg) ? false : $content;
	}
	
	/**
	 * Escapes path string
	 * 
	 * @param string $path
	 * @return string
	 */
	private function escapePath($path) {
		$out = $path;
		
		foreach ($this->escapeChars as $char) {
			$out = str_replace($char, '\\'.$char, $out);
		}
		
		return '"' . $out . '"';
	}
	
	/**
	 * close existed connection
	 * 
	 * @return boolean
	 */
	public function close() {
		unset($this->connection);
	}
	
	/** destructor */
	public function __destruct() {
		$this->close();
	}
}


