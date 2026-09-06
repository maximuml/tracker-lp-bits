<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Config\SiteConfig;
use App\Support\Environment;
use App\Support\Logger;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

class BackupService
{
    public const BACKUP_EXCLUDES = ['vendor', 'node_modules', '.git', '.idea', '.settings', '.DS_Store', '.github'];

    public const BACKUP_RETENTION_COUNT_DEFAULT = 10;

    public function __construct(
        private BackupTransferService $transferService,
    ) {}

    /**
     * @param  mixed  $method
     * @param  mixed  $transfer
     * @return array<int|string, mixed>
     */
    public function backupWeb($method = null, $transfer = false): array
    {
        $webRoot = base_path();
        $dirName = basename($webRoot);
        $excludes = self::BACKUP_EXCLUDES;
        $baseFilename = sprintf('%s/%s.web.%s', $this->getBackupExportPath(), $dirName, date('Ymd.His'));
        if (Environment::commandExists('tar') && ($method === 'tar' || $method === null)) {
            $filename = $baseFilename.'.tar.gz';
            $command = 'tar';
            foreach ($excludes as $item) {
                $command .= ' --exclude='.escapeshellarg("$dirName/$item");
            }
            $command .= sprintf(
                ' -czf %s -C %s %s 2>&1',
                escapeshellarg($filename),
                escapeshellarg(dirname($webRoot)),
                escapeshellarg($dirName)
            );
            $result = exec($command, $output, $result_code);
            Logger::writeWithContext((string) sprintf('command: %s, output: %s, result_code: %s, result: %s, filename: %s', $command, json_encode($output), $result_code, $result, $filename), (string) 'info', (bool) false);
        } else {
            // use php zip
            $filename = $baseFilename.'.zip';
            $zip = new \ZipArchive;
            $zipOpen = $zip->open($filename, \ZipArchive::CREATE);
            if ($zipOpen !== true) {
                throw new \RuntimeException("Can not open $filename, error: $zipOpen");
            }
            // create recursive directory iterator
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($webRoot, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
            // let's iterate
            foreach ($files as $name => $file) {
                $localeName = substr($name, strlen($webRoot) + 1);
                $start = strstr($localeName, DIRECTORY_SEPARATOR, true) ?: $localeName;
                // add a directory
                $localeName = $dirName.DIRECTORY_SEPARATOR.$localeName;
                if (! in_array($start, $excludes)) {
                    if (is_file($name)) {
                        $zip->addFile($name, $localeName);
                    } elseif (is_dir($name)) {
                        Logger::writeWithContext((string) "Is dir: {$name}.", (string) 'info', (bool) false);
                        $zip->addEmptyDir($localeName);
                    } else {
                        Logger::writeWithContext((string) "Not file or dir {$name}.", (string) 'error', (bool) false);
                    }
                }
            }
            $zip->close();
            $result_code = 0;
            Logger::writeWithContext((string) 'No tar command, use zip.', (string) 'info', (bool) false);
        }
        if (! $transfer) {
            return compact('result_code', 'filename');
        }

        return $this->transferService->transfer($filename, $result_code);
    }

    /**
     * @param  mixed  $transfer
     * @return array<int|string, mixed>
     */
    public function backupDatabase($transfer = false): array
    {
        $connectionName = config('database.default');
        $config = config("database.connections.$connectionName");
        $filename = sprintf('%s/%s.database.%s.sql', $this->getBackupExportPath(), basename(base_path()), date('Ymd.His'));
        $tmpFile = tempnam(sys_get_temp_dir(), 'db.cnf');
        if ($tmpFile === false) {
            throw new \RuntimeException('Could not create temporary database credentials file');
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

        $dumpCommand = Environment::commandExists('mariadb-dump') ? 'mariadb-dump' : 'mysqldump';
        $sslFlag = Environment::commandExists('mariadb-dump') ? '--ssl=0' : '--ssl-mode=DISABLED';
        $command = sprintf(
            '%s --defaults-extra-file=%s --single-transaction --no-create-db --no-tablespaces %s %s >> %s 2>&1',
            $dumpCommand,
            escapeshellarg($tmpFile),
            $sslFlag,
            escapeshellarg($config['database']),
            escapeshellarg($filename)
        );
        $result = exec($command, $output, $result_code);
        @unlink($tmpFile);
        Logger::writeWithContext((string) sprintf('command: %s, output: %s, result_code: %s, result: %s, filename: %s', $command, json_encode($output), $result_code, $result, $filename), (string) 'info', (bool) false);
        if (! $transfer) {
            return compact('result_code', 'filename');
        }

        return $this->transferService->transfer($filename, $result_code);
    }

    /**
     * @param  mixed  $method
     * @param  mixed  $transfer
     * @return array<int|string, mixed>
     */
    public function backupAll($method = null, $transfer = false): array
    {
        $backupWeb = $this->backupWeb($method);
        if ($backupWeb['result_code'] != 0) {
            throw new \RuntimeException('backup web fail: '.json_encode($backupWeb));
        }
        $backupDatabase = $this->backupDatabase();
        if ($backupDatabase['result_code'] != 0) {
            throw new \RuntimeException('backup database fail: '.json_encode($backupDatabase));
        }
        $baseFilename = sprintf('%s/%s.%s', $this->getBackupExportPath(), basename(base_path()), date('Ymd.His'));
        if (Environment::commandExists('tar') && ($method === 'tar' || $method === null)) {
            $filename = $baseFilename.'.tar.gz';
            $command = sprintf(
                'tar -czf %s -C %s %s -C %s %s 2>&1',
                escapeshellarg($filename),
                escapeshellarg(dirname($backupWeb['filename'])),
                escapeshellarg(basename($backupWeb['filename'])),
                escapeshellarg(dirname($backupDatabase['filename'])),
                escapeshellarg(basename($backupDatabase['filename']))
            );
            $result = exec($command, $output, $result_code);
            Logger::writeWithContext((string) sprintf('command: %s, output: %s, result_code: %s, result: %s, filename: %s', $command, json_encode($output), $result_code, $result, $filename), (string) 'info', (bool) false);
        } else {
            // use php zip
            $filename = $baseFilename.'.zip';
            $zip = new \ZipArchive;
            $zipOpen = $zip->open($filename, \ZipArchive::CREATE);
            if ($zipOpen !== true) {
                throw new \RuntimeException("Can not open $filename, error: $zipOpen");
            }
            $zip->addFile($backupWeb['filename'], basename($backupWeb['filename']));
            $zip->addFile($backupDatabase['filename'], basename($backupDatabase['filename']));
            $zip->close();
            $result_code = 0;
            Logger::writeWithContext((string) 'No tar command, use zip.', (string) 'info', (bool) false);
        }
        File::delete($backupWeb['filename']);
        File::delete($backupDatabase['filename']);

        // Optional GPG encryption of the backup archive
        $gpgRecipient = SiteConfig::current()->backup->gpgRecipient();
        if ($gpgRecipient !== '' && Environment::commandExists('gpg')) {
            $encryptedFile = $filename.'.gpg';
            $gpgCommand = sprintf(
                'gpg --batch --yes --trust-model always --recipient %s --encrypt %s 2>&1',
                escapeshellarg($gpgRecipient),
                escapeshellarg($filename)
            );
            $gpgResult = exec($gpgCommand, $gpgOutput, $gpgCode);
            Logger::writeWithContext((string) sprintf('GPG encrypt: command=%s, code=%s, output=%s', $gpgCommand, $gpgCode, json_encode($gpgOutput)), (string) 'info', (bool) false);
            if ($gpgCode === 0 && file_exists($encryptedFile)) {
                File::delete($filename);
                $filename = $encryptedFile;
            } else {
                throw new \RuntimeException('GPG encryption failed: '.json_encode($gpgOutput));
            }
        }

        if (! $transfer) {
            return compact('result_code', 'filename');
        }

        return $this->transferService->transfer($filename, $result_code);
    }

    private function getBackupExportPath(): string
    {
        $path = SiteConfig::current()->backup->exportPath();
        if (empty($path)) {
            $path = $this->getBackupExportPathDefault();
        }

        return $path;
    }

    public function getBackupExportPathDefault(): string
    {
        return storage_path('app/backups');
    }

    /**
     * do backup cronjob
     *
     * @param  mixed  $force
     * @return bool|array<int|string, mixed>
     */
    public function cronjobBackup($force = false): bool|array
    {
        $setting = SiteConfig::current()->backup->toArray();
        if ($setting['enabled'] != 'yes' && ! $force) {
            Logger::writeWithContext((string) 'Backup not enabled.', (string) 'info', (bool) false);

            return false;
        }
        $now = now();
        $frequency = $setting['frequency'];
        $settingHour = (int) $setting['hour'];
        $settingMinute = (int) $setting['minute'];
        $nowHour = (int) $now->format('H');
        $nowMinute = (int) $now->format('i');
        Logger::writeWithContext((string) ("Backup frequency: {$frequency}, force: ".strval($force)), (string) 'info', (bool) false);
        if (! $force) {
            if ($frequency == 'daily') {
                if ($settingHour != $nowHour) {
                    Logger::writeWithContext((string) sprintf('Backup setting hour: %s != now hour: %s', $settingHour, $nowHour), (string) 'info', (bool) false);

                    return false;
                }
                if ($settingMinute != $nowMinute) {
                    Logger::writeWithContext((string) sprintf('Backup setting minute: %s != now minute: %s', $settingMinute, $nowMinute), (string) 'info', (bool) false);

                    return false;
                }
            } elseif ($frequency == 'hourly') {
                if ($settingMinute != $nowMinute) {
                    Logger::writeWithContext((string) sprintf('Backup setting minute: %s != now minute: %s', $settingMinute, $nowMinute), (string) 'info', (bool) false);

                    return false;
                }
            } else {
                throw new \RuntimeException("Unknown backup frequency: $frequency");
            }
        }
        $backupResult = $this->backupAll();
        Logger::writeWithContext((string) ('Backup all result: '.json_encode($backupResult)), (string) 'info', (bool) false);
        $transferResult = $this->transferService->transfer($backupResult['filename'], $backupResult['result_code'], $setting);
        $backupResult['transfer_result'] = $transferResult;
        Logger::writeWithContext((string) ('[BACKUP_ALL_DONE]: '.json_encode($backupResult)), (string) 'info', (bool) false);
        $this->cleanupBackupFiles(basename($backupResult['filename']));

        return $backupResult;
    }

    /** @param  mixed  $basename */
    private function cleanupBackupFiles($basename): void
    {
        $nameParts = explode('.', $basename);
        $firstPart = $nameParts[0];
        $lastPart = $nameParts[count($nameParts) - 1];
        $retentionCount = SiteConfig::current()->backup->retentionCount();
        if ($retentionCount <= 0) {
            $retentionCount = self::BACKUP_RETENTION_COUNT_DEFAULT;
        }
        $path = $this->getBackupExportPath();
        $allFiles = collect(File::allFiles($path))->filter(function (SplFileInfo $file) use ($firstPart, $lastPart) {
            $name = basename($file->getRealPath());

            return str_starts_with($name, $firstPart) && str_ends_with($name, $lastPart);
        });
        // 按创建时间降序排序
        $allFiles = $allFiles->sortByDesc(fn (SplFileInfo $file) => $file->getCTime());
        $filesToDelete = $allFiles->slice($retentionCount);
        Logger::writeWithContext((string) sprintf('retentionCount: %s, path: %s, fileCount: %s', $retentionCount, $path, $allFiles->count()), (string) 'info', (bool) false);
        foreach ($filesToDelete as $file) {
            $realPath = $file->getRealPath();
            File::delete($realPath);
            Logger::writeWithContext((string) sprintf('delete backup file: %s', $realPath), (string) 'info', (bool) false);
        }
    }
}
