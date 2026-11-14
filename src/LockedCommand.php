<?php

namespace ADT\CommandLock;

use ADT\CommandLock\Storage\FileSystemStorage;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command that runs at most once at a time: a second run of the same command ends right away
 * with exit code 0 (a cron job just skips a run). Locks are files in the locks directory,
 * see FileSystemStorage.
 *
 * The command implements executeCommand() instead of execute(), the locks directory is set by
 * the DI container, e.g. in Nette:
 *
 *     decorator:
 *         ADT\CommandLock\LockedCommand:
 *             setup:
 *                 - setLocksDir(%rootDir%/data/locks)
 */
abstract class LockedCommand extends Command
{
	use CommandLock;

	private string $locksDir;

	abstract protected function executeCommand(InputInterface $input, OutputInterface $output): int;

	public function setLocksDir(string $locksDir): void
	{
		$this->locksDir = $locksDir;
	}

	/**
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->setStorage(new FileSystemStorage($this->locksDir));

		$this->lock();

		// Released also when the command throws - a file lock would be freed by the exit of
		// the process anyway, but not by a long-running process that catches the exception
		try {
			return $this->executeCommand($input, $output);
		} finally {
			$this->unlock();
		}
	}
}
