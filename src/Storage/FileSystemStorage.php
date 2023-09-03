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
			// If pgid can be retrieved, the process that owned the lock is still running
			if (posix_getpgid((int) file_get_contents($pidFilePath)) !== false) {
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