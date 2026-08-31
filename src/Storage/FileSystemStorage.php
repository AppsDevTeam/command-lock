<?php

namespace ADT\CommandLock\Storage;

use ADT\Utils\FileSystem;
use Exception;

class FileSystemStorage implements Storage
{
	private string $dir;
	
	public function __construct(string $dir)
	{
		if ($dir[strlen($dir) - 1] !== '/' && $dir[strlen($dir) - 1] !== '\\') {
			$dir .= '/';
		}
		
		$this->dir = $dir;
	}
	
	public function lock(string $key): bool
	{
		// folder containing all the locks
		FileSystem::createDirAtomically($this->dir);

		$pathName = $this->dir . $key;
		$pidFilePath =  $pathName . '/pid';

		if (file_exists($pathName)) {
			// A lock whose pid file is missing or unreadable is orphaned and must be
			// treated as stale. Reading a missing pid gives (int) false = 0, and
			// posix_getpgid(0) returns the current process group, so the lock looked
			// permanently held and the command silently ended on exit(0) forever.
			$pid = is_file($pidFilePath) ? (int) file_get_contents($pidFilePath) : 0;

			// The process that owned the lock is still running.
			if ($pid > 0 && posix_getpgid($pid) !== false) {
				return false;
			}

			self::rmdir($pathName);
		}

		if (FileSystem::createDirAtomically($pathName)) {
			if (file_put_contents($pidFilePath, getmypid())) {
				return true;
			}

			self::rmdir($pathName);
		}

		throw new Exception('Failed to acquire lock.');
	}

	public function unlock(string $key):bool
	{
		$pathName = $this->dir . $key;
		if (!file_exists($pathName) || self::rmdir($pathName)) {
			return true;
		}

		return false;
	}

	private static function rmdir(string $path): bool
	{
		@unlink($path . '/pid');
		return @rmdir($path);
	}
}