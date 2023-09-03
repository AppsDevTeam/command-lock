<?php
namespace ADT\CommandLock;

use ADT\CommandLock\Storage\Storage;
use Exception;

trait CommandLock
{
	abstract public function getName();

	private Storage $storage;
	private ?string $identifier = null;

	public function setStorage(Storage $storage)
	{
		$this->storage = $storage;
	}

	public function setIdentifier(string $identifier)
	{
		$this->identifier = $identifier;
	}

	/**
	 * @throws Exception
	 */
	private function lock(): void
	{
		if (!$this->storage->lock($this->getKey())) {
			exit(0);
		}
	}

	/**
	 * @throws Exception
	 */
	private function unlock(): void
	{
		if (!$this->storage->unlock($this->getKey())) {
			throw new Exception('Failed to release lock.');
		}
	}
	
	private function getKey()
	{
		return  preg_replace('/[^-a-zA-Z0-9]/', '-', $this->identifier ?: $this->getName());
	}
}
