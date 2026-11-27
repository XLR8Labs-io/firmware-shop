<?php

use App\Jobs\SendAdminAlert;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('pages carry the browser protection headers', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('in production, an unexpected error alerts the admins once an hour, not on every repeat', function () {
    app()->detectEnvironment(fn () => 'production');
    $error = new RuntimeException('Payment table is locked');

    report($error);
    report($error);

    Queue::assertPushed(SendAdminAlert::class, 1);
    Queue::assertPushed(SendAdminAlert::class, fn (SendAdminAlert $job): bool => str_contains($job->message, 'Payment table is locked'));
});

test('outside production, errors are only logged', function () {
    report(new RuntimeException('Local mistake'));

    Queue::assertNotPushed(SendAdminAlert::class);
});

describe('database backup', function () {
    beforeEach(function () {
        $this->storage = sys_get_temp_dir().'/it-firmware-backup-test-'.uniqid();
        app()->useStoragePath($this->storage);
    });

    afterEach(function () {
        File::deleteDirectory($this->storage);
    });

    test('the database is dumped, gzipped and old backups are removed', function () {
        $directory = $this->storage.'/app/private/backups';
        File::ensureDirectoryExists($directory);
        touch($old = $directory.'/database-2026-01-01-033000.sql.gz', now()->subDays(20)->getTimestamp());
        touch($recent = $directory.'/database-2026-11-20-033000.sql.gz', now()->subDays(7)->getTimestamp());

        Process::fake(function (PendingProcess $process) {
            $resultFile = collect($process->command)->first(fn (string $argument): bool => str_starts_with($argument, '--result-file='));
            file_put_contents(substr((string) $resultFile, strlen('--result-file=')), "CREATE TABLE `users` (id int);\n");

            return Process::result();
        });

        $this->artisan('app:backup-database')->assertSuccessful();

        $backups = File::glob($directory.'/database-*.sql.gz');

        expect($backups)->toHaveCount(2)
            ->and($backups)->toContain($recent)
            ->and($backups)->not->toContain($old)
            ->and(gzdecode((string) file_get_contents(collect($backups)->reject(fn ($path) => $path === $recent)->sole())))->toContain('CREATE TABLE `users`');

        Process::assertRan(fn (PendingProcess $process): bool => $process->command[0] === 'mysqldump'
            && in_array('--single-transaction', $process->command, true)
            && array_key_exists('MYSQL_PWD', $process->environment)
            && collect($process->command)->every(fn (string $argument): bool => ! str_starts_with($argument, '--password')));
    });

    test('a failed backup leaves no file and alerts the admins', function () {
        Process::fake(fn () => Process::result(errorOutput: 'Access denied for user', exitCode: 2));

        $this->artisan('app:backup-database')->assertFailed();

        expect(File::glob($this->storage.'/app/private/backups/*'))->toBe([]);
        Queue::assertPushed(SendAdminAlert::class, fn (SendAdminAlert $job): bool => str_contains($job->message, 'Access denied for user'));
    });
});
