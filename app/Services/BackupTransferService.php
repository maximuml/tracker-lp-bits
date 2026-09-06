<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Config\SiteConfig;
use App\Support\Logger;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

class BackupTransferService
{
    /**
     * @param  mixed  $filename
     * @param  mixed  $result_code
     * @param  mixed  $setting
     * @return array<int|string, mixed>
     */
    public function transfer($filename, $result_code, $setting = null): array
    {
        if ($result_code != 0) {
            throw new \RuntimeException("file: $filename backup fail!");
        }
        $result = compact('filename', 'result_code');
        if (empty($setting)) {
            $setting = SiteConfig::current()->backup->toArray();
        }

        $saveResult = $this->saveToFtp($setting, $filename);
        Logger::writeWithContext((string) "[BACKUP_FTP]: {$saveResult}", (string) 'info', (bool) false);
        $result['ftp'] = $saveResult;

        $saveResult = $this->saveToSftp($setting, $filename);
        Logger::writeWithContext((string) "[BACKUP_SFTP]: {$saveResult}", (string) 'info', (bool) false);
        $result['sftp'] = $saveResult;

        return $result;
    }

    /**
     * @param  array<int|string, mixed>  $setting
     * @param  mixed  $filename
     */
    private function saveToFtp(array $setting, $filename): bool|string
    {
        if ($setting['via_ftp'] !== 'yes') {
            Logger::writeWithContext((string) ("via_ftp !== 'yes', via_ftp: ".($setting['via_ftp'] ?? '')), (string) 'info', (bool) false);

            return false;
        }
        $config = config('filesystems.disks.ftp');
        if (empty($config)) {
            Logger::writeWithContext((string) 'No ftp config.', (string) 'info', (bool) false);

            return false;
        }
        foreach (['host', 'username', 'password', 'root'] as $item) {
            if (empty($config[$item])) {
                Logger::writeWithContext((string) "No ftp {$item}.", (string) 'info', (bool) false);

                return false;
            }
        }
        $disk = Storage::disk('ftp');

        return $this->doTransfer($disk, $filename);

    }

    /**
     * @param  array<int|string, mixed>  $setting
     * @param  mixed  $filename
     */
    public function saveToSftp(array $setting, $filename): bool|string
    {
        if ($setting['via_sftp'] !== 'yes') {
            Logger::writeWithContext((string) ("via_sftp !== 'yes', via_sftp: ".($setting['via_sftp'] ?? '')), (string) 'info', (bool) false);

            return false;
        }
        $config = config('filesystems.disks.sftp');
        if (empty($config)) {
            Logger::writeWithContext((string) 'No sftp config.', (string) 'info', (bool) false);

            return false;
        }
        foreach (['host', 'username', 'password', 'root'] as $item) {
            if (empty($config[$item])) {
                Logger::writeWithContext((string) "No sftp {$item}.", (string) 'info', (bool) false);

                return false;
            }
        }
        $disk = Storage::disk('sftp');

        return $this->doTransfer($disk, $filename);
    }

    /**
     * @param  mixed  $filename
     */
    private function doTransfer(FilesystemAdapter $remoteFilesystem, $filename): bool|string
    {
        $localAdapter = new LocalFilesystemAdapter('/');
        $localFilesystem = new Filesystem($localAdapter);
        $start = Carbon::now();
        try {
            $remoteFilesystem->writeStream(basename($filename), $localFilesystem->readStream($filename));
            $speed = ! (float) abs($start->diffInSeconds()) ? 0 : filesize($filename) / (float) abs($start->diffInSeconds());
            $log = 'Elapsed time: '.$start->diffForHumans(null, CarbonInterface::DIFF_ABSOLUTE);
            $log .= ', Speed: '.number_format($speed / 1024, 2).' KB/s';
            Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

            return true;
        } catch (\Throwable $exception) {
            Logger::writeWithContext((string) ('Transfer error: '.$exception->getMessage()), (string) 'error', (bool) false);

            return $exception->getMessage();
        }
    }
}
