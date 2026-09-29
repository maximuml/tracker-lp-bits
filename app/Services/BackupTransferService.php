<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Config\SiteConfig;
use App\Support\Logger;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Http;
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

        $saveResult = $this->saveToGdrive($setting, $filename);
        Logger::writeWithContext((string) "[BACKUP_GDRIVE]: {$saveResult}", (string) 'info', (bool) false);
        $result['gdrive'] = $saveResult;

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
     * Upload the backup archive to Google Drive using a service account.
     * Credentials come from the GDRIVE_* environment variables; the account
     * needs the drive.file scope and write access to GDRIVE_FOLDER_ID.
     *
     * @param  array<int|string, mixed>  $setting
     * @param  mixed  $filename
     */
    public function saveToGdrive(array $setting, $filename): bool|string
    {
        if (($setting['via_gdrive'] ?? 'no') !== 'yes') {
            Logger::writeWithContext((string) ("via_gdrive !== 'yes', via_gdrive: ".($setting['via_gdrive'] ?? '')), (string) 'info', (bool) false);

            return false;
        }
        $credentialsPath = (string) config('backup.gdrive_credentials', '');
        if ($credentialsPath === '' || ! is_file($credentialsPath)) {
            Logger::writeWithContext((string) 'No gdrive credentials file.', (string) 'info', (bool) false);

            return false;
        }
        try {
            $accessToken = $this->gdriveAccessToken($credentialsPath);
            $metadata = ['name' => basename($filename)];
            $folderId = (string) config('backup.gdrive_folder_id', '');
            if ($folderId !== '') {
                $metadata['parents'] = [$folderId];
            }
            $stream = fopen($filename, 'rb');
            if ($stream === false) {
                throw new \RuntimeException("Cannot open backup file: {$filename}");
            }
            try {
                $response = Http::withToken($accessToken)
                    ->attach('metadata', json_encode($metadata, JSON_THROW_ON_ERROR), 'metadata.json', ['Content-Type' => 'application/json; charset=UTF-8'])
                    ->attach('file', $stream, basename($filename), ['Content-Type' => 'application/gzip'])
                    ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
            } finally {
                fclose($stream);
            }
            if (! $response->successful()) {
                throw new \RuntimeException('Drive upload failed: '.$response->status().' '.$response->body());
            }

            return true;
        } catch (\Throwable $exception) {
            Logger::writeWithContext((string) ('GDrive transfer error: '.$exception->getMessage()), (string) 'error', (bool) false);

            return $exception->getMessage();
        }
    }

    /**
     * Exchange a service-account JSON key for a short-lived Drive access token
     * via the OAuth2 JWT bearer flow.
     */
    private function gdriveAccessToken(string $credentialsPath): string
    {
        $credentials = json_decode((string) file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);
        foreach (['client_email', 'private_key', 'token_uri'] as $key) {
            if (empty($credentials[$key]) || ! is_string($credentials[$key])) {
                throw new \RuntimeException("GDrive credentials missing {$key}");
            }
        }
        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'aud' => $credentials['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        if (! openssl_sign("{$header}.{$claims}", $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('GDrive JWT signing failed');
        }
        $assertion = "{$header}.{$claims}.".$this->base64UrlEncode($signature);
        $response = Http::asForm()->post($credentials['token_uri'], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);
        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new \RuntimeException('GDrive token exchange failed: '.$response->status().' '.$response->body());
        }

        return $token;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
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
            $remoteName = basename($filename);
            $remoteFilesystem->writeStream($remoteName, $localFilesystem->readStream($filename));
            // Some adapters report a failed write as a silent false rather
            // than throwing — verify the file actually landed (and is whole)
            // before reporting success.
            if (! $remoteFilesystem->fileExists($remoteName)) {
                throw new \RuntimeException("Remote file {$remoteName} missing after upload");
            }
            if ($remoteFilesystem->fileSize($remoteName) !== filesize($filename)) {
                throw new \RuntimeException("Remote file {$remoteName} size mismatch after upload");
            }
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
