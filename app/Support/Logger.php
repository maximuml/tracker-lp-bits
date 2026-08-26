<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Nexus\Nexus;

/**
 * Legacy file-log helpers extracted from `include/globalfunctions.php`.
 *
 * Phase 5 of the legacy migration. Mirrors `do_log()` and `getLogFile()`,
 * keeping the per-request static caches and the log-level filtering.
 */
final class Logger
{
    private static ?string $logLevel = null;

    private static ?string $appEnv = null;

    /** @var array<string, string> */
    private static array $filePaths = [];

    /**
     * @param  array<string, mixed>|null  $user
     */
    public static function write(string $log, string $level = 'info', bool $echo = false, ?array $user = null, ?string $passkey = null): void
    {
        if (self::$logLevel === null) {
            self::$logLevel = (string) Env::get('LOG_LEVEL', 'debug');
        }
        if (self::$appEnv === null) {
            self::$appEnv = (string) Env::get('APP_ENV', 'production');
        }

        $setLogLevelKey = self::logLevelIndex(self::$logLevel);
        $currentLogLevelKey = self::logLevelIndex($level);
        if ($currentLogLevelKey === false) {
            $level = 'error';
            $log = "[ERROR_LOG_LEVEL] $log";
            $currentLogLevelKey = self::logLevelIndex($level);
        }
        if ($setLogLevelKey === false || $currentLogLevelKey === false || $currentLogLevelKey < $setLogLevelKey) {
            return;
        }

        $logFile = self::filePath();
        if (($fd = fopen($logFile, 'a')) === false) {
            $log .= "--------Can not open $logFile";
            $fd = fopen(sys_get_temp_dir().'/nexus.log', 'a');
        }

        $uid = $user['id'] ?? 0;
        $passkey = $passkey ?? '';

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $nexus = Nexus::instance();
        $content = sprintf(
            '[%s] [%s] [%s] [%s] [%s] [%s] %s.%s %s:%s %s%s%s %s%s',
            Time::millis(true),
            $nexus ? $nexus->getRequestId() : 'NO_REQUEST_ID',
            $nexus ? $nexus->getLogSequence() : 0,
            sprintf('%.3f', microtime(true) - ($nexus ? $nexus->getStartTimestamp() : 0)),
            $uid,
            $passkey,
            self::$appEnv,
            strtoupper($level),
            $backtrace[0]['file'] ?? '',
            $backtrace[0]['line'] ?? '',
            $backtrace[1]['class'] ?? '',
            $backtrace[1]['type'] ?? '',
            $backtrace[1]['function'] ?? '',
            $log,
            PHP_EOL
        );
        if (is_resource($fd)) {
            fwrite($fd, $content);
            fclose($fd);
        }
        if ($echo) {
            echo $content.PHP_EOL;
        }
        if ($nexus) {
            $nexus->incrementLogSequence();
        }
    }

    /**
     * Write a log line, resolving the current user/passkey from the
     * legacy or Laravel request context. Mirrors the legacy `do_log()`.
     */
    public static function writeWithContext(string $log, string $level = 'info', bool $echo = false): void
    {
        $user = null;
        $passkey = '';

        if (defined('IN_NEXUS') && IN_NEXUS) {
            $curUser = app(CurrentUser::class)->get();
            if (is_array($curUser) && ! empty($curUser)) {
                $user = $curUser;
                $passkey = (string) ($curUser['passkey'] ?? '');
            }
            if ($passkey === '') {
                $passkey = (string) (request()->input('passkey') ?? request()->input('authkey') ?? '');
            }
        } else {
            try {
                $authUser = Auth::user();
                if ($authUser instanceof Model) {
                    $user = $authUser->getAttributes();
                    $passkey = (string) ($authUser->getAttribute('passkey') ?? '');
                }
            } catch (\Throwable $exception) {
                $passkey = '!NO_AUTH';
            }
        }

        self::write($log, $level, $echo, $user, $passkey);
    }

    public static function filePath(string $append = ''): string
    {
        if (isset(self::$filePaths[$append])) {
            return self::$filePaths[$append];
        }

        $std = ['php://stdout', 'php://stderr'];

        $path = getenv('NEXUS_LOG_DIR', true);
        if (in_array($path, $std, true)) {
            return self::$filePaths[$append] = $path;
        }

        $fromEnv = true;
        if ($path === false) {
            $fromEnv = false;
            $path = sys_get_temp_dir();
        }

        $logFileFromDotEnv = Env::get('LOG_FILE');
        if (! $fromEnv && $logFileFromDotEnv && in_array($logFileFromDotEnv, $std, true)) {
            return self::$filePaths[$append] = $logFileFromDotEnv;
        }

        $logFile = rtrim($path, '/').'/nexus.log';
        if (! $fromEnv && $logFileFromDotEnv) {
            $logFile = $logFileFromDotEnv;
        }

        $lastDotPos = strrpos($logFile, '.');
        if ($lastDotPos !== false) {
            $prefix = substr($logFile, 0, $lastDotPos);
            $suffix = substr($logFile, $lastDotPos);
        } else {
            $prefix = $logFile;
            $suffix = '';
        }

        $name = $prefix;
        if ($append !== '') {
            $name .= "-$append";
        }

        if (Environment::isConsole()) {
            $scriptUserInfo = posix_getpwuid(posix_getuid());
            $name .= sprintf('-cli-%s', $scriptUserInfo['name'] ?? 'unknown');
        }

        $name .= '-'.date('Y-m-d');

        return self::$filePaths[$append] = $name.$suffix;
    }

    /**
     * Map a log-level string to its numeric severity, or false when unknown.
     */
    private static function logLevelIndex(string $level): int|false
    {
        return match ($level) {
            'debug' => 0,
            'info' => 1,
            'notice' => 2,
            'warning' => 3,
            'error' => 4,
            'critical' => 5,
            'alert' => 6,
            'emergency' => 7,
            default => false,
        };
    }
}
