<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\Files\Storage;

require_once __DIR__ . '/../3rdparty/smb4php/smb.php';

class SMB extends \OC\Files\Storage\StreamWrapper{
	private $password;
	private $user;
	private $host;
	private $root;
	private $share;

	public function __construct($params) {
		if (isset($params['host']) && isset($params['user']) && isset($params['password']) && isset($params['share'])) {
			$this->host=$params['host'];
			$this->user=$params['user'];
			$this->password=$params['password'];
			$this->share=$params['share'];
			$this->root=isset($params['root'])?$params['root']:'/';
			if ( ! $this->root || $this->root[0]!='/') {
				$this->root='/'.$this->root;
			}
			if (substr($this->root, -1, 1)!='/') {
				$this->root.='/';
			}
			if ( ! $this->share || $this->share[0]!='/') {
				$this->share='/'.$this->share;
			}
			if (substr($this->share, -1, 1)=='/') {
				$this->share = substr($this->share, 0, -1);
			}
		} else {
			throw new \Exception();
		}
	}

	private function handleError($errno, $errstr, $errfile, $errline, array $errcontext) {
		// error was suppressed with the @-operator
		if (error_reporting() === 0) {
			return false;
		}

		throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
	}

	public function getId(){
		return 'smb::' . $this->user . '@' . $this->host . '/' . $this->share . '/' . $this->root;
	}

	public function constructUrl($path) {
		if (substr($path, -1)=='/') {
			$path = substr($path, 0, -1);
		}
		if (substr($path, 0, 1)=='/') {
			$path = substr($path, 1);
		}
		// remove trailing dots which some versions of samba don't seem to like
		$path = rtrim($path, '.');
		$path = urlencode($path);
		$user = urlencode($this->user);
		$pass = urlencode($this->password);
		return 'smb://'.$user.':'.$pass.'@'.$this->host.$this->share.$this->root.$path;
	}

	public function stat($path) {

		// make sure that we catch all errors and warnings so that we can remove
		// sensible data like username and password before writing it to the log
		set_error_handler(array($this, 'handleError'), E_ALL);

		try {
			if ( ! $path and $this->root=='/') {//mtime doesn't work for shares
				$stat=stat($this->constructUrl($path));
				if (!empty($stat)) {
					$mtime=$this->shareMTime();
					$stat['mtime']=$mtime;
				}
			} else {
				$stat = stat($this->constructUrl($path));
			}
		} catch (\Exception $e) {
			\OCP\Util::writeLog('files_external', $e->getMessage(), \OCP\Util::ERROR);
		}

		restore_error_handler();

		// smb4php can return an empty array if the connection could not be established
		if (empty($stat)) {
			return false;
		}

		return $stat;
	}

	/**
	 * Unlinks file or directory
	 * @param string @path
	 */
	public function unlink($path) {
		if ($this->is_dir($path)) {
			$this->rmdir($path);
		}
		else {
			$url = $this->constructUrl($path);
			unlink($url);
			clearstatcache(false, $url);
		}
		// smb4php still returns false even on success so
		// check here whether file was really deleted
		return !file_exists($path);
	}

	/**
	 * check if a file or folder has been updated since $time
	 * @param string $path
	 * @param int $time
	 * @return bool
	 */
	public function hasUpdated($path,$time) {
		if(!$path and $this->root=='/') {
			// mtime doesn't work for shares, but giving the nature of the backend,
			// doing a full update is still just fast enough
			return true;
		} else {
			$actualTime=$this->filemtime($path);
			return $actualTime>$time;
		}
	}

	/**
	 * get the best guess for the modification time of the share
	 */
	private function shareMTime() {
		$dh=$this->opendir('');
		$lastCtime=0;
		if(is_resource($dh)) {
			while (($file = readdir($dh)) !== false) {
				if ($file!='.' and $file!='..') {
					$ctime=$this->filemtime($file);
					if ($ctime>$lastCtime) {
						$lastCtime=$ctime;
					}
				}
			}
		}
		return $lastCtime;
	}
}
