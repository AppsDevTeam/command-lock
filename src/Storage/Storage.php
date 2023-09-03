<?php

namespace ADT\CommandLock\Storage;

interface Storage
{
	public function lock(string $key);
	public function unlock(string $key);
}