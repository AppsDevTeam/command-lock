# command-lock

```php

namespace App\Commands;

use ADT\CommandLock\CommandLock;
use ADT\CommandLock\Storage\FileSystemStorage;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Command extends \Symfony\Component\Console\Command\Command
{
	use CommandLock;

	private string $locksDir;

	abstract protected function executeCommand(InputInterface $input, OutputInterface $output): int;

	public function setLocksDir(string $locksDir)
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

		$status = $this->executeCommand($input, $output);

		$this->unlock();

		return $status;
	}
}
```

## FileSystemStorage

Locks are taken with `flock()` on `<locksDir>/<key>.lock`. The kernel releases the lock when the process exits (even after `kill -9`), so there are no stale locks. Lock files are never deleted.

Child processes inherit the lock: a process started from the locked command (`exec()`, `system()`, `proc_open()`) keeps the lock until it exits, even after the command itself has finished.

The locks directory and the lock files are created with permissions `0770` and `0660` regardless of umask, so processes of different users sharing a group can use the same locks. Set the setgid bit on the locks directory (`chmod g+s`) to make new files inherit its group.

A shared locks directory locks across hosts only if its filesystem forwards `flock()` to a common lock server. GlusterFS mounted via FUSE does: a lock taken on one host blocks the same command on the other hosts, a released or killed holder frees it for them immediately (tested on Gluster 11.1, replica 3 arbiter 1, also from a docker container with a bind mount). When a host crashes without closing its connection, Gluster releases its locks only after `network.ping-timeout` (42 s by default). NFS depends on the version and the lock daemon – verify it before relying on it.
