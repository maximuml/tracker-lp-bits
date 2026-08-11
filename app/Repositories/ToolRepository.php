<?php
namespace App\Repositories;

use App\Http\Middleware\Locale;
use App\Models\Invite;
use App\Models\Message;
use App\Models\News;
use App\Models\Poll;
use App\Models\PollAnswer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nexus\Database\NexusDB;
use Nexus\Plugin\Plugin;
use NexusPlugin\Permission\PermissionRepository;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class ToolRepository extends BaseRepository
{
    const BACKUP_EXCLUDES = ['vendor', 'node_modules', '.git', '.idea', '.settings', '.DS_Store', '.github'];

    const BACKUP_RETENTION_COUNT_DEFAULT = 10;

    /**
     * @param  mixed  $method
     * @param  mixed  $transfer
     * @return  array<int|string, mixed>
     */
    public function backupWeb($method = null, $transfer = false): array
    {
        $webRoot = base_path();
        $dirName = basename($webRoot);
        $excludes = self::BACKUP_EXCLUDES;
        $baseFilename = sprintf('%s/%s.web.%s', $this->getBackupExportPath(), $dirName, date('Ymd.His'));
        if (command_exists('tar') && ($method === 'tar' || $method === null)) {
            $filename = $baseFilename . ".tar.gz";
            $command = "tar";
            foreach ($excludes as $item) {
                $command .= ' --exclude=' . escapeshellarg("$dirName/$item");
            }
            $command .= sprintf(
                ' -czf %s -C %s %s 2>&1',
                escapeshellarg($filename),
                escapeshellarg(dirname($webRoot)),
                escapeshellarg($dirName)
            );
            $result = exec($command, $output, $result_code);
            do_log(sprintf(
                "command: %s, output: %s, result_code: %s, result: %s, filename: %s",
                $command, json_encode($output), $result_code, $result, $filename
            ));
        } else {
            //use php zip
            $filename = $baseFilename . ".zip";
            $zip = new \ZipArchive();
            $zipOpen = $zip->open($filename, \ZipArchive::CREATE);
            if ($zipOpen !== true) {
                throw new \RuntimeException("Can not open $filename, error: $zipOpen");
            }
            // create recursive directory iterator
            $files = new \RecursiveIteratorIterator (new \RecursiveDirectoryIterator($webRoot, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
            // let's iterate
            foreach ($files as $name => $file) {
                $localeName = substr($name, strlen($webRoot) + 1);
                $start = strstr($localeName, DIRECTORY_SEPARATOR, true) ?: $localeName;
                //add a directory
                $localeName = $dirName . DIRECTORY_SEPARATOR . $localeName;
                if (!in_array($start, $excludes)) {
                    if (is_file($name)) {
                        $zip->addFile($name, $localeName);
                    } elseif (is_dir($name)) {
                        do_log("Is dir: $name.");
                        $zip->addEmptyDir($localeName);
                    } else {
                        do_log("Not file or dir $name.", 'error');
                    }
                }
            }
            $zip->close();
            $result_code = 0;
            do_log("No tar command, use zip.");
        }
        if (!$transfer) {
            return compact('result_code', 'filename');
        }
        return $this->transfer($filename, $result_code);
    }

    /**
     * @param  mixed  $transfer
     * @return  array<int|string, mixed>
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

        $dumpCommand = command_exists('mariadb-dump') ? 'mariadb-dump' : 'mysqldump';
        $sslFlag = command_exists('mariadb-dump') ? '--ssl=0' : '--ssl-mode=DISABLED';
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
        do_log(sprintf(
            "command: %s, output: %s, result_code: %s, result: %s, filename: %s",
            $command, json_encode($output), $result_code, $result, $filename
        ));
        if (!$transfer) {
            return compact('result_code', 'filename');
        }
        return $this->transfer($filename, $result_code);
    }

    /**
     * @param  mixed  $method
     * @param  mixed  $transfer
     * @return  array<int|string, mixed>
     */
    public function backupAll($method = null, $transfer = false): array
    {
        $backupWeb = $this->backupWeb($method);
        if ($backupWeb['result_code'] != 0) {
            throw new \RuntimeException("backup web fail: " . json_encode($backupWeb));
        }
        $backupDatabase = $this->backupDatabase();
        if ($backupDatabase['result_code'] != 0) {
            throw new \RuntimeException("backup database fail: " . json_encode($backupDatabase));
        }
        $baseFilename = sprintf('%s/%s.%s', $this->getBackupExportPath(), basename(base_path()), date('Ymd.His'));
        if (command_exists('tar') && ($method === 'tar' || $method === null)) {
            $filename = $baseFilename . ".tar.gz";
            $command = sprintf(
                'tar -czf %s -C %s %s -C %s %s 2>&1',
                escapeshellarg($filename),
                escapeshellarg(dirname($backupWeb['filename'])),
                escapeshellarg(basename($backupWeb['filename'])),
                escapeshellarg(dirname($backupDatabase['filename'])),
                escapeshellarg(basename($backupDatabase['filename']))
            );
            $result = exec($command, $output, $result_code);
            do_log(sprintf(
                "command: %s, output: %s, result_code: %s, result: %s, filename: %s",
                $command, json_encode($output), $result_code, $result, $filename
            ));
        } else {
            //use php zip
            $filename = $baseFilename . ".zip";
            $zip = new \ZipArchive();
            $zipOpen = $zip->open($filename, \ZipArchive::CREATE);
            if ($zipOpen !== true) {
                throw new \RuntimeException("Can not open $filename, error: $zipOpen");
            }
            $zip->addFile($backupWeb['filename'], basename($backupWeb['filename']));
            $zip->addFile($backupDatabase['filename'], basename($backupDatabase['filename']));
            $zip->close();
            $result_code = 0;
            do_log("No tar command, use zip.");
        }
        File::delete($backupWeb['filename']);
        File::delete($backupDatabase['filename']);
        if (!$transfer) {
            return compact('result_code', 'filename');
        }
        return $this->transfer($filename, $result_code);
    }

    private function getBackupExportPath(): string
    {
        $path = \App\Support\Config\SiteConfig::current()->backup->exportPath();
        if (empty($path)) {
            $path = self::getBackupExportPathDefault();
        }
        return $path;
    }

    public static function getBackupExportPathDefault(): string
    {
        return sys_get_temp_dir() . "/nexusphp_backup";
    }

    /**
     * do backup cronjob
     * @param  mixed  $force
     * @return  bool|array<int|string, mixed>
     */
    public function cronjobBackup($force = false): bool|array
    {
        $setting = \App\Support\Config\SiteConfig::current()->backup->toArray();
        if ($setting['enabled'] != 'yes' && !$force) {
            do_log("Backup not enabled.");
            return false;
        }
        $now = now();
        $frequency = $setting['frequency'];
        $settingHour = (int)$setting['hour'];
        $settingMinute = (int)$setting['minute'];
        $nowHour = (int)$now->format('H');
        $nowMinute = (int)$now->format('i');
        do_log("Backup frequency: $frequency, force: " . strval($force));
        if (!$force) {
            if ($frequency == 'daily') {
                if ($settingHour != $nowHour) {
                    do_log(sprintf('Backup setting hour: %s != now hour: %s', $settingHour, $nowHour));
                    return false;
                }
                if ($settingMinute != $nowMinute) {
                    do_log(sprintf('Backup setting minute: %s != now minute: %s', $settingMinute, $nowMinute));
                    return false;
                }
            } elseif ($frequency == 'hourly') {
                if ($settingMinute != $nowMinute) {
                    do_log(sprintf('Backup setting minute: %s != now minute: %s', $settingMinute, $nowMinute));
                    return false;
                }
            } else {
                throw new \RuntimeException("Unknown backup frequency: $frequency");
            }
        }
        $backupResult = $this->backupAll();
        do_log("Backup all result: " . json_encode($backupResult));
        $transferResult = $this->transfer($backupResult['filename'], $backupResult['result_code'], $setting);
        $backupResult['transfer_result'] = $transferResult;
        do_log("[BACKUP_ALL_DONE]: " . json_encode($backupResult));
        $this->cleanupBackupFiles(basename($backupResult['filename']));
        return $backupResult;
    }

    /**
     * @param  mixed  $filename
     * @param  mixed  $result_code
     * @param  mixed  $setting
     * @return  array<int|string, mixed>
     */
    public function transfer($filename, $result_code, $setting = null): array
    {
        if ($result_code != 0) {
            throw new \RuntimeException("file: $filename backup fail!");
        }
        $result = compact('filename', 'result_code');
        if (empty($setting)) {
            $setting = \App\Support\Config\SiteConfig::current()->backup->toArray();
        }

        $saveResult = $this->saveToFtp($setting, $filename);
        do_log("[BACKUP_FTP]: $saveResult");
        $result['ftp'] = $saveResult;

        $saveResult = $this->saveToSftp($setting, $filename);
        do_log("[BACKUP_SFTP]: $saveResult");
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
            do_log("via_ftp !== 'yes', via_ftp: " . ($setting['via_ftp'] ?? ''));
            return false;
        }
        $config = config('filesystems.disks.ftp');
        if (empty($config)) {
            do_log("No ftp config.");
            return false;
        }
        foreach (['host', 'username', 'password', 'root'] as $item) {
            if (empty($config[$item])) {
                do_log("No ftp $item.");
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
            do_log("via_sftp !== 'yes', via_sftp: " . ($setting['via_sftp'] ?? ''));
            return false;
        }
        $config = config('filesystems.disks.sftp');
        if (empty($config)) {
            do_log("No sftp config.");
            return false;
        }
        foreach (['host', 'username', 'password', 'root'] as $item) {
            if (empty($config[$item])) {
                do_log("No sftp $item.");
                return false;
            }
        }
        $disk = Storage::disk('sftp');
        return $this->doTransfer($disk, $filename);
    }

    /**
     * @param  \Illuminate\Filesystem\FilesystemAdapter  $remoteFilesystem
     * @param  mixed  $filename
     */
    private function doTransfer(\Illuminate\Filesystem\FilesystemAdapter $remoteFilesystem, $filename): bool|string
    {
        $localAdapter = new \League\Flysystem\Local\LocalFilesystemAdapter('/');
        $localFilesystem = new \League\Flysystem\Filesystem($localAdapter);
        $start = Carbon::now();
        try {
            $remoteFilesystem->writeStream(basename($filename), $localFilesystem->readStream($filename));
            $speed = !(float)abs($start->diffInSeconds()) ? 0 :filesize($filename) / (float)abs($start->diffInSeconds());
            $log =  'Elapsed time: '.$start->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE);
            $log .= ', Speed: '. number_format($speed/1024,2) . ' KB/s';
            do_log($log);
            return true;
        } catch (\Throwable $exception) {
            do_log("Transfer error: " . $exception->getMessage(), 'error');
            return $exception->getMessage();
        }
    }

    /** @param  mixed  $basename */
    private function cleanupBackupFiles($basename): void
    {
        $nameParts = explode('.', $basename);
        $firstPart = $nameParts[0];
        $lastPart = $nameParts[count($nameParts) - 1];
        $retentionCount = \App\Support\Config\SiteConfig::current()->backup->retentionCount();
        if ($retentionCount <= 0) {
            $retentionCount = self::BACKUP_RETENTION_COUNT_DEFAULT;
        }
        $path = self::getBackupExportPath();
        $allFiles = collect(File::allFiles($path))->filter(function (\Symfony\Component\Finder\SplFileInfo $file) use ($firstPart, $lastPart) {
             $name = basename($file->getRealPath());
             return str_starts_with($name, $firstPart) && str_ends_with($name, $lastPart);
        });
        // 按创建时间降序排序
        $allFiles = $allFiles->sortByDesc(fn (\Symfony\Component\Finder\SplFileInfo $file) => $file->getCTime());
        $filesToDelete = $allFiles->slice($retentionCount);
        do_log(sprintf(
            "retentionCount: %s, path: %s, fileCount: %s",
            $retentionCount, $path, $allFiles->count()
        ));
        foreach ($filesToDelete as $file) {
            $realPath = $file->getRealPath();
            File::delete($realPath);
            do_log(sprintf("delete backup file: %s", $realPath));
        }
    }

    /**
     * @param  mixed  $to
     * @param  mixed  $subject
     * @param  mixed  $body
     * @param  mixed  $exception
     * @return  bool
     */
    public function sendMail($to, $subject, $body, $exception = false): bool
    {
        $log = "[SEND_MAIL]";
        $factory = new EsmtpTransportFactory();
        $smtpConfig = \App\Support\Config\SiteConfig::fromDb()->smtp;
        do_log("$log, to: $to, subject: $subject, body: $body, smtp: " . json_encode($smtpConfig->toArray()));
        $encryption = $smtpConfig->encryption();
        if ($encryption !== null && !in_array($encryption, ['ssl', 'tls'])) {
            $encryption = null;
        }
        $smtpPort = $smtpConfig->port();
        $smtpAddress = $smtpConfig->address();
        $accountName = $smtpConfig->accountName() ?: null;
        $accountPassword = $smtpConfig->accountPassword() ?: null;
        $port = $smtpPort !== '' ? (int) $smtpPort : null;
        // Create the Transport
        $transport = $factory->create(new Dsn(
            $port === 465 && $encryption !== null ? 'smtps' : 'smtp',
            $smtpAddress,
            $accountName,
            $accountPassword,
            $port,
            ['verify_peer' => false]
        ));

        // Create the Mailer using your created Transport
        $mailer = new Mailer($transport);

        // Create a message
        $message = (new Email())
            ->from(new Address(\App\Support\Config\SiteConfig::current()->main->siteEmail(), \App\Support\Config\SiteConfig::current()->basic->siteName()))
            ->to($to)
            ->subject($subject)
            ->text($body)
            ->html(nl2br($body))
        ;

        // Send the message
        try {
            $mailer->send($message);
            return true;
        } catch (\Throwable $e) {
            do_log("$log, fail: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'error');
            if ($exception) {
                throw $e;
            } else {
                return false;
            }
        }
    }

    /**
     * @param  \App\Models\User  $user
     * @return  array<int|string, mixed>
     */
    public function getNotificationCount(User $user): array
    {
        $result = [];
        //attend or not
        $attendRep = new AttendanceRepository();
        $attendance = $attendRep->getAttendance($user->id, date('Ymd'));
        $result['attendance'] = $attendance ? 0 : 1;

        //unread news
        $count = News::query()->where('added', '>', $user->last_home ?? '1970-01-01 00:00:00')->count();
        $result['news'] = $count;

        //unread messages
        $count = Message::query()->where('receiver', $user->id)->where('unread', 'yes')->count();
        $result['message'] = $count;

        //un-vote poll
        $total = Poll::query()->count();
        $userVoteCount = PollAnswer::query()->where('userid', $user->id)->selectRaw('count(distinct(pollid)) as counts')->first()->counts;
        $result['poll'] = $total - $userVoteCount;

        return $result;
    }

    /**
     * @param  mixed  $class
     * @return  array<int|string, mixed>
     */
    public static function listUserClassPermissions($class): array
    {
        $settings = \App\Support\Config\SiteConfig::current()->authority->toArray();
        $result = [];
        foreach ($settings as $permission => $minClass) {
            if ($minClass >= User::CLASS_PEASANT && $minClass <= $class) {
                $result[] = $permission;
            }
        }
        return $result;
    }

    /**
     * @param  mixed  $uid
     * @return  array<int|string, mixed>
     */
    public static function listUserAllPermissions($uid): array
    {
        static $uidPermissionsCached = [];
        if (isset($uidPermissionsCached[$uid])) {
            return $uidPermissionsCached[$uid];
        }
        $log = "uid: $uid";
        $userInfo = \App\Support\UserDisplay::row($uid);
        $class = $userInfo['class'];

        //Class permission
        $classPermissions = self::listUserClassPermissions($class);

        //Role permission
        $rolePermissions = apply_filter("user_role_permissions", [], $uid);

        //Direct permission
        $directPermissions = apply_filter("user_direct_permissions", [], $uid);

        $allPermissions = array_merge($classPermissions, $rolePermissions, $directPermissions);
        do_log("$log, allPermissions: " . json_encode($allPermissions));
        $result = array_combine($allPermissions, $allPermissions);
        $uidPermissionsCached[$uid] = $result;
        return $result;
    }

    /**
     * @param  array<int|string, mixed>  $hashArr
     * @param  int  $total
     * @param  int  $left
     * @param  int  $deep
     * @return  array<int|string, mixed>
     */
    public function generateUniqueInviteHash(array $hashArr, int $total, int $left, int $deep = 0): array
    {
        do_log("total: $total, left: $left, deep: $deep");
        if ($deep > 10) {
            throw new \RuntimeException("deep: $deep > 10");
        }
        if (count($hashArr) >= $total) {
            return array_slice(array_values($hashArr), 0, $total);
        }
        for ($i = 0; $i < $left; $i++) {
            $hash = Str::random(32);
            $hashArr[$hash] =  $hash;
        }
        $exists = Invite::query()->whereIn('hash', array_values($hashArr))->get(['id', 'hash']);
        foreach($exists as $value) {
            unset($hashArr[$value->hash]);
        }
        return $this->generateUniqueInviteHash($hashArr, $total, $total - count($hashArr), ++$deep);

    }

    /** @return  mixed */
    public function removeDuplicateSnatch()
    {
        $size = 2000;
        $stickyPromotionParticipatorsTable = 'sticky_promotion_participators';
        $claimTable = "claims";
        $hitAndRunTable = "hit_and_runs";
        $stickyPromotionExists = NexusDB::hasTable($stickyPromotionParticipatorsTable);
        $claimTableExists = NexusDB::hasTable($claimTable);
        $hitAndRunTableExists = NexusDB::hasTable($hitAndRunTable);
        $idsField = NexusDB::groupConcatField('id');
        while (true) {
            $snatchRes = NexusDB::table('snatched')
                ->select('userid', 'torrentid', NexusDB::raw("$idsField as ids"))
                ->groupBy('userid', 'torrentid')
                ->havingRaw('count(*) > 1')
                ->limit($size)
                ->get();
            if (empty($snatchRes)) {
                break;
            }
            do_log("[DELETE_DUPLICATED_SNATCH], count: " . count($snatchRes));
            foreach ($snatchRes as $snatchRow) {
                $torrentId = $snatchRow['torrentid'];
                $userId = $snatchRow['userid'];
                $idArr = explode(',', $snatchRow['ids']);
                sort($idArr, SORT_NUMERIC);
                $remainId = array_pop($idArr);
                do_log("[DELETE_DUPLICATED_SNATCH], torrent: $torrentId, user: $userId, snatchIdStr: " . implode(',', $idArr));
                if (! empty($idArr)) {
                    NexusDB::table('snatched')->whereIn('id', $idArr)->delete();
                }
                if ($claimTableExists) {
                    NexusDB::table($claimTable)->where('torrent_id', $torrentId)->where('uid', $userId)->update(['snatched_id' => $remainId]);
                }
                if ($hitAndRunTableExists) {
                    NexusDB::table($hitAndRunTable)->where('torrent_id', $torrentId)->where('uid', $userId)->update(['snatched_id' => $remainId]);
                }
                if ($stickyPromotionExists) {
                    NexusDB::table($stickyPromotionParticipatorsTable)->where('torrent_id', $torrentId)->where('uid', $userId)->update(['snatched_id' => $remainId]);
                }
            }
        }
    }

    /** @return  mixed */
    public function removeDuplicatePeer()
    {
        $size = 2000;
        $idsField = NexusDB::groupConcatField('id');
        while (true) {
            $results = NexusDB::table('peers')
                ->select('torrent', 'userid', NexusDB::raw("$idsField as ids"))
                ->groupBy('torrent', 'peer_id', 'userid')
                ->havingRaw('count(*) > 1')
                ->limit($size)
                ->get();
            if (empty($results)) {
                do_log("[DELETE_DUPLICATED_PEERS], no data");
                break;
            }
            do_log("[DELETE_DUPLICATED_PEERS], count: " . count($results));
            foreach ($results as $row) {
                $torrentId = $row['torrent'];
                $userId = $row['userid'];
                $idArr = explode(',', $row['ids']);
                sort($idArr, SORT_NUMERIC);
                $remainId = array_pop($idArr);
                do_log("[DELETE_DUPLICATED_PEERS], torrent: $torrentId, user: $userId, snatchIdStr: " . implode(',', $idArr));
                if (! empty($idArr)) {
                    NexusDB::table('peers')->whereIn('id', $idArr)->delete();
                }
            }
        }
    }

    /**
     * @param  string  $subjectTransKey
     * @param  array<int|string, mixed>  $subjectTransContext
     * @param  string  $msgTransKey
     * @param  array<int|string, mixed>  $msgTransContext
     */
    public function sendAlarmEmail(string $subjectTransKey, array $subjectTransContext, string $msgTransKey, array $msgTransContext): void
    {
        $receiverUid = \App\Support\Config\SiteConfig::current()->system->alarmEmailReceiver();
        if (empty($receiverUid)) {
            $locale = Locale::getDefault();
            $subject = nexus_trans($subjectTransKey, $subjectTransContext, $locale);
            $msg = nexus_trans($msgTransKey, $msgTransContext, $locale);
            do_log(sprintf("%s - %s", $subject, $msg), "error");
        } else {
            $receiverUidArr = preg_split("/[\r\n\s,，]+/", $receiverUid);
            $users = User::query()->whereIn("id", $receiverUidArr)->get(User::$commonFields);
            foreach ($users as $user) {
                $locale = $user->locale;
                $subject = nexus_trans($subjectTransKey, $subjectTransContext, $locale);
                $msg = nexus_trans($msgTransKey, $msgTransContext, $locale);
                $result = $this->sendMail($user->email, $subject, $msg);
                do_log(sprintf("send msg: %s result: %s", $msg, var_export($result, true)), $result ? "info" : "error");
            }
        }
    }
}
