<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\ToolRepository;
use App\Support\Environment;
use App\Support\Logger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackupRestoreDrill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:restore-drill
                            {--latest : Use the most recent backup file}
                            {--file= : Path to a specific .sql backup file}
                            {--test-db= : Temporary database name for restore verification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify backup integrity by restoring the latest SQL dump into a temporary database and checking table counts.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sqlFile = $this->findBackupFile();
        if ($sqlFile === null) {
            $this->error('No backup SQL file found. Run backup:database first.');

            return 1;
        }

        $this->info("Using backup file: {$sqlFile}");
        $fileSize = filesize($sqlFile);
        if ($fileSize === false || $fileSize < 100) {
            $this->error("Backup file appears empty or too small ({$fileSize} bytes).");

            return 1;
        }
        $this->info("File size: {$fileSize} bytes");

        $testDb = (string) ($this->option('test-db') ?: 'nexusphp_restore_test');
        $connectionName = config('database.default');
        $config = config("database.connections.{$connectionName}");

        $credsFile = $this->createTempCredsFile($config);

        try {
            $this->info("Creating temporary database: {$testDb}");
            $this->execMysql($credsFile, "CREATE DATABASE IF NOT EXISTS `{$testDb}`");

            $this->info("Restoring backup into {$testDb}...");
            $restoreCommand = $this->buildRestoreCommand($credsFile, $sqlFile, $testDb);
            $output = [];
            $resultCode = 0;
            exec($restoreCommand, $output, $resultCode);

            if ($resultCode !== 0) {
                $this->error('Restore failed: '.implode("\n", $output));

                return 1;
            }

            $count = $this->getTableCount($credsFile, $testDb);

            $this->info("Restored tables: {$count}");

            if ($count === 0) {
                $this->error('Restore verification failed: no tables found in temporary database.');

                return 1;
            }

            $this->info("Restore drill PASSED: {$count} tables restored successfully.");
            Logger::writeWithContext(
                (string) "Backup restore drill passed: {$count} tables from {$sqlFile}",
                (string) 'info',
                (bool) false
            );

            return 0;
        } finally {
            $this->info("Cleaning up temporary database: {$testDb}");
            $this->execMysql($credsFile, "DROP DATABASE IF EXISTS `{$testDb}`");
            @unlink($credsFile);
        }
    }

    private function execMysql(string $credsFile, string $sql): void
    {
        $client = Environment::commandExists('mariadb') ? 'mariadb' : 'mysql';
        $sslFlag = Environment::commandExists('mariadb') ? '--ssl=0' : '--ssl-mode=DISABLED';
        $command = sprintf(
            '%s --defaults-extra-file=%s %s -e %s 2>&1',
            $client,
            escapeshellarg($credsFile),
            $sslFlag,
            escapeshellarg($sql)
        );
        exec($command, $output, $resultCode);
        if ($resultCode !== 0) {
            throw new \RuntimeException('MySQL command failed: '.implode("\n", $output));
        }
    }

    private function getTableCount(string $credsFile, string $testDb): int
    {
        $client = Environment::commandExists('mariadb') ? 'mariadb' : 'mysql';
        $sslFlag = Environment::commandExists('mariadb') ? '--ssl=0' : '--ssl-mode=DISABLED';
        $command = sprintf(
            '%s --defaults-extra-file=%s %s -N -e %s 2>&1',
            $client,
            escapeshellarg($credsFile),
            $sslFlag,
            escapeshellarg("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$testDb}'")
        );
        $output = [];
        exec($command, $output, $resultCode);
        if ($resultCode !== 0) {
            return 0;
        }

        return (int) ($output[0] ?? 0);
    }

    private function findBackupFile(): ?string
    {
        $file = $this->option('file');
        if ($file && File::exists($file)) {
            return $file;
        }

        $rep = app(ToolRepository::class);
        $path = $rep->getBackupExportPathDefault();
        if (! is_dir($path)) {
            return null;
        }

        $files = collect(File::allFiles($path))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.sql'))
            ->sortByDesc(fn ($f) => $f->getCTime());

        return $files->first()?->getRealPath() ?: null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function createTempCredsFile(array $config): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'db_restore.cnf');
        if ($tmpFile === false) {
            throw new \RuntimeException('Could not create temporary credentials file');
        }
        $optionContent = sprintf(
            "[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n",
            $config['username'],
            $config['password'],
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 3306
        );
        file_put_contents($tmpFile, $optionContent);
        chmod($tmpFile, 0600);

        return $tmpFile;
    }

    private function buildRestoreCommand(string $credsFile, string $sqlFile, string $testDb): string
    {
        $client = Environment::commandExists('mariadb') ? 'mariadb' : 'mysql';
        $sslFlag = Environment::commandExists('mariadb') ? '--ssl=0' : '--ssl-mode=DISABLED';

        return sprintf(
            '%s --defaults-extra-file=%s %s %s < %s 2>&1',
            $client,
            escapeshellarg($credsFile),
            $sslFlag,
            escapeshellarg($testDb),
            escapeshellarg($sqlFile)
        );
    }
}
