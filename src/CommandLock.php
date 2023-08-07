<?php
namespace ADT\CommandLock;

use ADT\Utils\FileSystem;
use Exception;

trait CommandLock
{
	abstract public function getName();

	private string $lockPath;

	/**
	 * @param string $path Directory with locks
	 * @param string $format Should only be file name, any instances of `$cmd$` are replaced with $commandName
	 *		in subsequent getPath and getFolder calls
	 * @throws Exception
	 */
	public function setCommandLockPath(string $path, string $format = '$cmd$.lock')
	{
		if ($path[strlen($path) - 1] !== '/' && $path[strlen($path) - 1] !== '\\') {
			$path .= '/';
		}
		if (strpos($path, '$cmd$') !== false) {
			throw new Exception("Path can't include \$cmd\$");
		}
		if (strpos($format, '$cmd$') === false) {
			$this->lockPath = $path . '.$cmd$.lock';
		}
		else {
			$this->lockPath = $path . $format;
		}
	}

	/**
	 * Tries to create a lock file with its process id.
	 *
	 * @param string|null $identifier If set, adds a "-$identifier" suffix to the name of the lock file.
	 * @return bool true in case of success, false otherwise
	 * @throws Exception
	 */
	protected function tryLock(?string $identifier = null): bool
	{
		// folder containg all the locks
		$folderName = $this->getFolder($identifier);
		FileSystem::createDirAtomically($folderName);

		$pathName = $this->getPath($identifier);
		$pidFilePath =  $pathName . '/pid';

		if (file_exists($pathName)) {
			// If pgid can be retrieved, the process that owned the lock is still running
			if (posix_getpgid((int) file_get_contents($pidFilePath)) !== false) {
				return false;
			}

			rmdir($pathName);
		}

		if (FileSystem::createDirAtomically($pathName)) {
			if (file_put_contents($pidFilePath, getmypid())) {
				return true;
			}

			rmdir($pathName);
		}

		throw new Exception('CommandLock: Failed to create lock');
	}

	/**
	 * Tries to unlock a lock.
	 *
	 * @param string|null $identifier If set, adds a "-$identifier" suffix to the name of the lock file
	 * @return bool true in case of success, false otherwise
	 * @throws Exception
	 */
	protected function tryUnlock(?string $identifier = null): bool
	{
		$pathName = $this->getPath($identifier);

		if (!file_exists($pathName) || rmdir($pathName)) {
			return true;
		}

		throw new Exception('CommandLock: Failed to remove lock.');
	}

	private function getPath($identifier = null)
	{
		$fullName = static::getFullName($this->getName(), $identifier);
		return $this->getFormattedLockPath($fullName);
	}

	private function getFolder($identifier = null)
	{
		$path = $this->getPath($identifier);
		return substr($path, 0, strripos($path, '/') + 1);
	}

	private function getFormattedLockPath(string $commandName = '')
	{
		$commandName = preg_replace('/[^-a-zA-Z0-9]/', '-', $commandName);
		return str_replace('$cmd$', $commandName, $this->lockPath);
	}

	private static function getFullName($name, $identifier): string
	{
		return $name . (is_string($identifier) ? "-$identifier" : '');
	}
}
