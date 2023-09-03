<?php

namespace ADT\CommandLock\Storage;

use Predis\Client;

class RedisStorage implements  Storage
{
	private Client $client;
	private int $lockTimeout = 2;
	private ?string $prefix;

	public function __construct(string $host	, int $port = 6379, ?string $password = null,  ?string $prefix = null)
	{
		$this->client = new Client([
			'host' => $host,
			'port' => $port,
			'password' => $password
		]);
		$this->prefix = $prefix;
	}

	public function lock(string $key)
	{
		$key = $this->prefix . $key;

		$response = $this->client->set($key, 1, 'NX', 'EX', $this->lockTimeout);
		if ((string)$response !== 'OK') {
			return false;
		}

		$pid = pcntl_fork();
		if ($pid == -1) {
			throw new \Exception("Could not fork.");
		} elseif ($pid) {
			// Parent process
			// Do nothing; just return and let the parent process continue its work
			return true;
		} else {
			// Child process
			while (true) {
				$this->client->expire($key, $this->lockTimeout);
				usleep($this->lockTimeout / 2 * 1000000);
			}
		}
	}

	public function unlock(string $key)
	{
		return $this->client->del([$this->prefix . $key]);
	}
}