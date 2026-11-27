<?php

namespace App\Console\Commands;

use App\Jobs\SendAdminAlert;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use RuntimeException;

#[Signature('app:backup-database {--keep=14 : How many days of backups to keep}')]
#[Description('Dump the database to a gzipped file in storage/app/private/backups and remove old dumps')]
class BackupDatabase extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory);

        $dumpPath = $directory.'/database-'.now()->format('Y-m-d-His').'.sql';

        try {
            $this->dump($dumpPath);
            $backupPath = $this->compress($dumpPath);
        } catch (RuntimeException $exception) {
            File::delete([$dumpPath, $dumpPath.'.gz']);
            SendAdminAlert::dispatch('The nightly database backup failed: '.Str::limit($exception->getMessage(), 300));
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $removed = $this->removeBackupsOlderThan(max(1, (int) $this->option('keep')), $directory);

        $this->components->info('Saved '.basename($backupPath).' ('.Number::fileSize((int) filesize($backupPath), precision: 1).'). Removed '.$removed.' old backup(s).');

        return self::SUCCESS;
    }

    /**
     * A consistent snapshot without locking the shop: --single-transaction for InnoDB tables.
     */
    private function dump(string $path): void
    {
        $connection = (array) config('database.connections.'.config('database.default'));
        $socket = $connection['unix_socket'] ?? null;

        $arguments = [
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--user='.$connection['username'],
            '--result-file='.$path,
            ...(filled($socket) ? ['--socket='.$socket] : ['--host='.$connection['host'], '--port='.$connection['port']]),
            (string) $connection['database'],
        ];

        // The password goes in the environment, so it never shows up in the process list.
        $result = Process::env(['MYSQL_PWD' => (string) $connection['password']])
            ->timeout(3600)
            ->run($arguments);

        if ($result->failed() || ! is_file($path)) {
            throw new RuntimeException('mysqldump failed: '.trim($result->errorOutput() ?: $result->output()));
        }
    }

    private function compress(string $path): string
    {
        $source = fopen($path, 'rb');
        $target = gzopen($path.'.gz', 'wb6');

        if ($source === false || $target === false) {
            throw new RuntimeException("Could not compress {$path}.");
        }

        while (! feof($source)) {
            gzwrite($target, (string) fread($source, 1024 * 1024));
        }

        fclose($source);
        gzclose($target);
        File::delete($path);

        return $path.'.gz';
    }

    private function removeBackupsOlderThan(int $days, string $directory): int
    {
        $cutoff = now()->subDays($days)->getTimestamp();
        $removed = 0;

        foreach (File::glob($directory.'/database-*.sql.gz') as $file) {
            if (filemtime($file) < $cutoff) {
                File::delete($file);
                $removed++;
            }
        }

        return $removed;
    }
}
