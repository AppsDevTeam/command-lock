# command-lock

Extend `LockedCommand` and implement `executeCommand()` instead of `execute()`. A second run of
the same command while the first one is still running ends right away with exit code 0, so a
cron job just skips a run.

```php
namespace App\Console\Commands;

use ADT\CommandLock\LockedCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:import')]
class ImportCommand extends LockedCommand
{
	protected function executeCommand(InputInterface $input, OutputInterface $output): int
	{
		// ...
		return self::SUCCESS;
	}
}
```

The locks directory is set by the DI container, in Nette by a decorator:

```neon
decorator:
	ADT\CommandLock\LockedCommand:
		setup:
			- setLocksDir(%rootDir%/data/locks)
```

The lock key is the name of the command; `setIdentifier()` replaces it (e.g. one lock per tenant).
The lock is released also when `executeCommand()` throws.

Another base class or another storage: use the `CommandLock` trait directly, `setStorage()`,
then `lock()` and `unlock()` around the work.

## FileSystemStorage

Locks are taken with `flock()` on `<locksDir>/<key>.lock`. The kernel releases the lock when the process exits (even after `kill -9`), so there are no stale locks. Lock files are never deleted.

Child processes inherit the lock: a process started from the locked command (`exec()`, `system()`, `proc_open()`) keeps the lock until it exits, even after the command itself has finished.

The locks directory and the lock files are created with permissions `0770` and `0660` regardless of umask, so processes of different users sharing a group can use the same locks. Set the setgid bit on the locks directory (`chmod g+s`) to make new files inherit its group.

A shared locks directory locks across hosts only if its filesystem forwards `flock()` to a common lock server. GlusterFS mounted via FUSE does: a lock taken on one host blocks the same command on the other hosts, a released or killed holder frees it for them immediately (tested on Gluster 11.1, replica 3 arbiter 1, also from a docker container with a bind mount). When a host crashes without closing its connection, Gluster releases its locks only after `network.ping-timeout` (42 s by default). NFS depends on the version and the lock daemon – verify it before relying on it.
