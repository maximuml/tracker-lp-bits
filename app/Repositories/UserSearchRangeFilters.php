<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Contracts\Database\Query\Builder;
use InvalidArgumentException;

/**
 * Numeric/date range where-clauses for the administrative user search
 * (ratio, uploaded, downloaded, joined, last access).
 */
final class UserSearchRangeFilters
{
    private const UNIT = 1073741824;

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyRatio(Builder $userQuery, array $params): array
    {
        $ratio = trim((string) ($params['r'] ?? ''));
        if (! $ratio) {
            return [];
        }
        $fragments = [];
        if ($ratio == '---') {
            $userQuery->where('u.uploaded', 0)->where('u.downloaded', 0);
        } elseif (strtolower(substr($ratio, 0, 3)) == 'inf') {
            $userQuery->where('u.uploaded', '>', 0)->where('u.downloaded', 0);
        } else {
            if (! is_numeric($ratio) || $ratio < 0) {
                throw new InvalidArgumentException('Bad ratio.');
            }
            $ratiotype = (string) ($params['rt'] ?? '');
            $fragments[] = 'rt='.$ratiotype;
            $userQuery->where('u.downloaded', '>', 0);
            if ($ratiotype == '3') {
                $ratio2 = trim((string) ($params['r2'] ?? ''));
                if ($ratio2 == '') {
                    throw new InvalidArgumentException('Two ratios needed for this type of search.');
                }
                if (! is_numeric($ratio2) || $ratio2 < $ratio) {
                    throw new InvalidArgumentException('Bad second ratio.');
                }
                $userQuery->whereRaw('(u.uploaded/u.downloaded) BETWEEN ? AND ?', [(float) $ratio, (float) $ratio2]);
                $fragments[] = 'r2='.$ratio2;
            } elseif ($ratiotype == '2') {
                $userQuery->whereRaw('(u.uploaded/u.downloaded) < ?', [(float) $ratio]);
            } elseif ($ratiotype == '1') {
                $userQuery->whereRaw('(u.uploaded/u.downloaded) > ?', [(float) $ratio]);
            } else {
                $userQuery->whereRaw('(u.uploaded/u.downloaded) BETWEEN ? AND ?', [max(0, (float) $ratio - 0.004), (float) $ratio + 0.004]);
            }
        }
        $fragments[] = 'r='.$ratio;

        return $fragments;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyUploaded(Builder $userQuery, array $params): array
    {
        $ul = trim((string) ($params['ul'] ?? ''));
        if (! $ul) {
            return [];
        }
        if (! is_numeric($ul) || $ul < 0) {
            throw new InvalidArgumentException('Bad uploaded amount.');
        }
        $ultype = (string) ($params['ult'] ?? '');
        $fragments = ['ult='.$ultype];
        if ($ultype == '3') {
            $ul2 = trim((string) ($params['ul2'] ?? ''));
            if ($ul2 == '') {
                throw new InvalidArgumentException('Two uploaded amounts needed for this type of search.');
            }
            if (! is_numeric($ul2) || $ul2 < $ul) {
                throw new InvalidArgumentException('Bad second uploaded amount.');
            }
            $userQuery->whereBetween('u.uploaded', [(float) $ul * self::UNIT, (float) $ul2 * self::UNIT]);
            $fragments[] = 'ul2='.$ul2;
        } elseif ($ultype == '2') {
            $userQuery->where('u.uploaded', '<', (float) $ul * self::UNIT);
        } elseif ($ultype == '1') {
            $userQuery->where('u.uploaded', '>', (float) $ul * self::UNIT);
        } else {
            $userQuery->whereBetween('u.uploaded', [max(0, ((float) $ul - 0.004) * self::UNIT), ((float) $ul + 0.004) * self::UNIT]);
        }
        $fragments[] = 'ul='.$ul;

        return $fragments;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyDownloaded(Builder $userQuery, array $params): array
    {
        $dl = trim((string) ($params['dl'] ?? ''));
        if (! $dl) {
            return [];
        }
        if (! is_numeric($dl) || $dl < 0) {
            throw new InvalidArgumentException('Bad downloaded amount.');
        }
        $dltype = (string) ($params['dlt'] ?? '');
        $fragments = ['dlt='.$dltype];
        if ($dltype == '3') {
            $dl2 = trim((string) ($params['dl2'] ?? ''));
            if ($dl2 == '') {
                throw new InvalidArgumentException('Two downloaded amounts needed for this type of search.');
            }
            if (! is_numeric($dl2) || $dl2 < $dl) {
                throw new InvalidArgumentException('Bad second downloaded amount.');
            }
            $userQuery->whereBetween('u.downloaded', [(float) $dl * self::UNIT, (float) $dl2 * self::UNIT]);
            $fragments[] = 'dl2='.$dl2;
        } elseif ($dltype == '2') {
            $userQuery->where('u.downloaded', '<', (float) $dl * self::UNIT);
        } elseif ($dltype == '1') {
            $userQuery->where('u.downloaded', '>', (float) $dl * self::UNIT);
        } else {
            $userQuery->whereBetween('u.downloaded', [max(0, ((float) $dl - 0.004) * self::UNIT), ((float) $dl + 0.004) * self::UNIT]);
        }
        $fragments[] = 'dl='.$dl;

        return $fragments;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyJoined(Builder $userQuery, array $params): array
    {
        $d = trim((string) ($params['d'] ?? ''));
        if (! $d) {
            return [];
        }
        $date = $this->parseDate($d);
        if ($date === null) {
            throw new InvalidArgumentException('Invalid date.');
        }
        $fragments = ['d='.$date];
        $datetype = (string) ($params['dt'] ?? '');
        $fragments[] = 'dt='.$datetype;
        if ($datetype == '0') {
            $userQuery->whereBetween('u.added', [$date, date('Y-m-d H:i:s', strtotime($date) + 86400)]);
        } elseif ($datetype == '3') {
            $d2 = trim((string) ($params['d2'] ?? ''));
            $date2 = $this->parseDate($d2);
            if ($date2 === null) {
                throw new InvalidArgumentException('Two dates needed for this type of search.');
            }
            $fragments[] = 'd2='.$date2;
            $userQuery->whereBetween('u.added', [$date, $date2]);
        } elseif ($datetype == '1') {
            $userQuery->where('u.added', '<', $date);
        } elseif ($datetype == '2') {
            $userQuery->where('u.added', '>', $date);
        }

        return $fragments;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyLastAccess(Builder $userQuery, array $params): array
    {
        $ls = trim((string) ($params['ls'] ?? ''));
        if (! $ls) {
            return [];
        }
        $last = $this->parseDate($ls);
        if ($last === null) {
            throw new InvalidArgumentException('Invalid date.');
        }
        $fragments = ['ls='.$last];
        $lasttype = (string) ($params['lst'] ?? '');
        $fragments[] = 'lst='.$lasttype;
        if ($lasttype == '0') {
            $userQuery->whereBetween('u.last_access', [$last, date('Y-m-d H:i:s', strtotime($last) + 86400)]);
        } elseif ($lasttype == '3') {
            $ls2 = trim((string) ($params['ls2'] ?? ''));
            $last2 = $this->parseDate($ls2);
            if ($last2 === null) {
                throw new InvalidArgumentException('The second date is not valid.');
            }
            $fragments[] = 'ls2='.$last2;
            $userQuery->whereBetween('u.last_access', [$last, $last2]);
        } elseif ($lasttype == '1') {
            $userQuery->where('u.last_access', '<', $last);
        } elseif ($lasttype == '2') {
            $userQuery->where('u.last_access', '>', $last);
        }

        return $fragments;
    }

    private function parseDate(string $date): ?string
    {
        if (str_contains($date, '-')) {
            $a = explode('-', $date);
        } elseif (str_contains($date, '/')) {
            $a = explode('/', $date);
        } else {
            return null;
        }

        if (count($a) !== 3) {
            return null;
        }

        for ($i = 0; $i < 3; $i++) {
            if (! is_numeric($a[$i])) {
                return null;
            }
        }

        if (checkdate((int) $a[1], (int) $a[2], (int) $a[0])) {
            $timestamp = mktime(0, 0, 0, (int) $a[1], (int) $a[2], (int) $a[0]);

            return $timestamp === false ? null : date('Y-m-d', $timestamp);
        }

        return null;
    }
}
