<?php

namespace App\Repositories;

use DateTime;
use Illuminate\Support\Facades\DB;

final class MysqlStatsRepository
{
    /** @var list<string> */
    private static array $byteUnits = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];

    private static string $datefmt = '%B %d, %Y at %I:%M %p';

    /** @var list<string> */
    private static array $month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /** @var list<string> */
    private static array $dayOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    /**
     * @return array<string, mixed>
     */
    public static function status(): array
    {
        $serverStatus = [];
        foreach (DB::select('SHOW STATUS') as $row) {
            $row = (array) $row;
            $serverStatus[$row['Variable_name']] = $row['Value'];
        }

        $queryStats = [];
        foreach ($serverStatus as $name => $value) {
            if (str_starts_with($name, 'Com_')) {
                $queryStats[str_replace('_', ' ', substr($name, 4))] = $value;
                unset($serverStatus[$name]);
            }
        }

        $uptime = (int) ($serverStatus['Uptime'] ?? 0);
        $bytesReceived = (float) ($serverStatus['Bytes_received'] ?? 0);
        $bytesSent = (float) ($serverStatus['Bytes_sent'] ?? 0);
        $connections = (int) ($serverStatus['Connections'] ?? 0);
        $abortedConnects = (int) ($serverStatus['Aborted_connects'] ?? 0);
        $abortedClients = (int) ($serverStatus['Aborted_clients'] ?? 0);
        $questions = (int) ($serverStatus['Questions'] ?? 0);

        unset(
            $serverStatus['Bytes_received'],
            $serverStatus['Bytes_sent'],
            $serverStatus['Connections'],
            $serverStatus['Aborted_connects'],
            $serverStatus['Aborted_clients'],
            $serverStatus['Questions'],
            $serverStatus['Uptime']
        );

        return [
            'uptimeSeconds' => $uptime,
            'startTime' => time() - $uptime,
            'bytesReceived' => $bytesReceived,
            'bytesSent' => $bytesSent,
            'totalBytes' => $bytesReceived + $bytesSent,
            'connections' => $connections,
            'abortedConnects' => $abortedConnects,
            'abortedClients' => $abortedClients,
            'questions' => $questions,
            'queryStats' => $queryStats,
            'serverStatus' => $serverStatus,
        ];
    }

    /**
     * @return list{string, string}
     */
    public static function formatByteDown(float $value, int $limes = 6, int $comma = 0): array
    {
        $dh = 10 ** $comma;
        $li = 10 ** $limes;
        $returnValue = $value;
        $unit = self::$byteUnits[0];

        for ($d = 6, $ex = 15; $d >= 1; $d--, $ex -= 3) {
            if (isset(self::$byteUnits[$d]) && $value >= $li * 10 ** $ex) {
                $value = round($value / (1024 ** $d / $dh)) / $dh;
                $unit = self::$byteUnits[$d];
                break;
            }
        }

        if ($unit !== self::$byteUnits[0]) {
            $returnValue = number_format($value, $comma, '.', ',');
        } else {
            $returnValue = number_format($value, 0, '.', ',');
        }

        return [$returnValue, $unit];
    }

    public static function timespanFormat(int $seconds): string
    {
        $days = floor($seconds / 86400);
        if ($days > 0) {
            $seconds -= $days * 86400;
        }

        $hours = floor($seconds / 3600);
        if ($days > 0 || $hours > 0) {
            $seconds -= $hours * 3600;
        }

        $minutes = floor($seconds / 60);
        if ($days > 0 || $hours > 0 || $minutes > 0) {
            $seconds -= $minutes * 60;
        }

        return (string) $days.' Days '.(string) $hours.' Hours '.(string) $minutes.' Minutes '.(string) $seconds.' Seconds ';
    }

    public static function localisedDate(int $timestamp = -1, string $format = ''): string
    {
        if ($format === '') {
            $format = self::$datefmt;
        }

        if ($timestamp === -1) {
            $timestamp = time();
        }

        $dt = new DateTime;
        $dt->setTimestamp($timestamp);

        $result = '';
        $len = strlen($format);
        for ($i = 0; $i < $len; $i++) {
            if ($format[$i] === '%' && $i + 1 < $len) {
                $token = $format[$i + 1];
                $replacement = match ($token) {
                    'a', 'A' => self::$dayOfWeek[(int) $dt->format('w')],
                    'b', 'B', 'h' => self::$month[(int) $dt->format('n') - 1],
                    'd' => $dt->format('d'),
                    'e' => str_pad($dt->format('j'), 2, ' ', STR_PAD_LEFT),
                    'm' => $dt->format('m'),
                    'Y' => $dt->format('Y'),
                    'y' => $dt->format('y'),
                    'H' => $dt->format('H'),
                    'I' => $dt->format('h'),
                    'l' => str_pad($dt->format('g'), 2, ' ', STR_PAD_LEFT),
                    'k' => str_pad($dt->format('G'), 2, ' ', STR_PAD_LEFT),
                    'M' => $dt->format('i'),
                    'S' => $dt->format('s'),
                    's' => (string) $timestamp,
                    'p' => $dt->format('A'),
                    'P' => $dt->format('a'),
                    'u' => $dt->format('N'),
                    'w' => $dt->format('w'),
                    'W' => $dt->format('W'),
                    'j' => str_pad((string) ((int) $dt->format('z') + 1), 3, '0', STR_PAD_LEFT),
                    'z' => $dt->format('O'),
                    'Z' => $dt->format('T'),
                    'c' => $dt->format('c'),
                    'r' => $dt->format('h:i:s A'),
                    'R' => $dt->format('H:i'),
                    'T' => $dt->format('H:i:s'),
                    'F' => $dt->format('Y-m-d'),
                    'D' => $dt->format('m/d/y'),
                    'x' => $dt->format('Y-m-d'),
                    'X' => $dt->format('H:i:s'),
                    'n' => "\n",
                    't' => "\t",
                    '%' => '%',
                    default => null,
                };
                if ($replacement !== null) {
                    $result .= $replacement;
                    $i++;

                    continue;
                }
            }
            $result .= $format[$i];
        }

        return $result;
    }
}
