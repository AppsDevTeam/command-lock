<?php

namespace ADT\CommandLock\Storage;

use Exception;

class FileSystemStorage implements Storage
{
	private const DIR_MODE = 0770;
	private const FILE_MODE = 0660;

	private string $dir;

	/** @var resource[] */
	private array $handles = [];

	public function __construct(string $dir)
	{
		if ($dir[strlen($dir) - 1] !== '/' && $dir[strlen($dir) - 1] !== '\\') {
			$dir .= '/';
		}

		$this->dir = $dir;
	}

	public function lock(string $key): bool
	{
		$path = $this->dir . $key . '.lock';

		// The lock file is never deleted, so the kernel lock is always taken on the same
		// inode. It only has to be created once, with permissions independent of umask.
		if (!is_file($path)) {
			$this->createDir(rtrim($this->dir, '/\\'));
			$this->createFile($path);
		}

		// flock() does not need write access, so a lock file created by another user
		// can be locked as long as it is readable.
		$handle = @fopen($path, 'r');
		if ($handle === false) {
			throw new Exception('Failed to acquire lock.');
		}

		// The kernel releases the lock when the process exits, even after kill -9,
		// so there are no stale locks to detect and remove.
		if (!flock($handle, LOCK_EX | LOCK_NB)) {
			fclose($handle);
			return false;
		}

		// The lock is held only as long as the handle stays open.
		$this->handles[$key] = $handle;

		return true;
	}

	public function unlock(string $key): bool
	{
		if (!isset($this->handles[$key])) {
			return true;
		}

		$result = flock($this->handles[$key], LOCK_UN);
		fclose($this->handles[$key]);
		unset($this->handles[$key]);

		return $result;
	}

	/**
	 * Prepares the directory under a temporary name with the final permissions
	 * and publishes it with rename(), so it never exists with umask permissions.
	 */
	private function createDir(string $dir): void
	{
		if (is_dir($dir)) {
			return;
		}

		$this->createDir(dirname($dir));

		$tmp = $this->getTmpPath($dir);
		if (!@mkdir($tmp) || !@chmod($tmp, self::DIR_MODE)) {
			@rmdir($tmp);
			throw new Exception('Failed to create lock directory "' . $dir . '".');
		}

		// Fails when another process has already created the directory and put
		// a lock file in it; an existing empty directory is just replaced.
		if (!@rename($tmp, $dir)) {
			@rmdir($tmp);
			if (!is_dir($dir)) {
				throw new Exception('Failed to create lock directory "' . $dir . '".');
			}
		}
	}

	/**
	 * Prepares the file under a temporary name with the final permissions and
	 * publishes it with link(), which, unlike rename(), fails if the file exists.
	 * Replacing an existing lock file would let two processes lock different inodes.
	 */
	private function createFile(string $path): void
	{
		$tmp = $this->getTmpPath($path);
		$handle = @fopen($tmp, 'x');
		if ($handle === false) {
			throw new Exception('Failed to create lock file "' . $path . '".');
		}
		fclose($handle);

		$created = @chmod($tmp, self::FILE_MODE) && @link($tmp, $path);
		@unlink($tmp);

		if (!$created && !is_file($path)) {
			throw new Exception('Failed to create lock file "' . $path . '".');
		}
	}

	private function getTmpPath(string $path): string
	{
		return $path . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
	}
}
