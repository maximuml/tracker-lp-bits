<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Validators;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Administrative user search query builder, migrated from usersearch_content.php.
 */
final class UserSearchRepository
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{count: int, rows: array<int, array<string, mixed>>, q: string}
     */
    public static function administrativeSearch(array $params, bool $hasModcomment, int $perPage = 30): array
    {
        $userQuery = DB::table('users as u');
        $q = '';
        $unit = 1073741824;

        if (count($params) > 0 && empty($params['h'])) {
            // name
            $n = trim((string) ($params['n'] ?? ''));
            if ($n !== '') {
                $names = explode(' ', $n);
                $names_inc = [];
                $names_exc = [];
                foreach ($names as $name) {
                    if (substr($name, 0, 1) == '~') {
                        if ($name == '~') {
                            continue;
                        }
                        $names_exc[] = substr($name, 1);
                    } else {
                        $names_inc[] = $name;
                    }
                }
                if (! empty($names_inc)) {
                    $userQuery->where(function ($query) use ($names_inc) {
                        $first = true;
                        foreach ($names_inc as $name) {
                            if (! self::hasWildcard($name)) {
                                $method = $first ? 'where' : 'orWhere';
                                $query->$method('u.username', $name);
                            } else {
                                $name = str_replace(['?', '*'], ['_', '%'], $name);
                                $method = $first ? 'where' : 'orWhere';
                                $query->$method('u.username', 'like', $name);
                            }
                            $first = false;
                        }
                    });
                }
                if (! empty($names_exc)) {
                    $userQuery->where(function ($query) use ($names_exc) {
                        foreach ($names_exc as $name) {
                            if (! self::hasWildcard($name)) {
                                $query->where('u.username', '!=', $name);
                            } else {
                                $name = str_replace(['?', '*'], ['_', '%'], $name);
                                $query->where('u.username', 'not like', $name);
                            }
                        }
                    });
                }
                $q = self::appendQueryParam($q, 'n='.rawurlencode($n));
            }

            // email
            $em = trim((string) ($params['em'] ?? ''));
            if ($em !== '') {
                $emaila = explode(' ', $em);
                $userQuery->where(function ($query) use ($emaila) {
                    $first = true;
                    foreach ($emaila as $email) {
                        if (strpos($email, '*') === false && strpos($email, '?') === false && strpos($email, '%') === false) {
                            if (! Validators::isEmail($email)) {
                                throw new InvalidArgumentException('Bad email.');
                            }
                            $method = $first ? 'where' : 'orWhere';
                            $query->$method('u.email', $email);
                        } else {
                            $sql_email = str_replace(['?', '*'], ['_', '%'], $email);
                            $method = $first ? 'where' : 'orWhere';
                            $query->$method('u.email', 'like', $sql_email);
                        }
                        $first = false;
                    }
                });
                $q = self::appendQueryParam($q, 'em='.rawurlencode($em));
            }

            // class
            $class = ((int) ($params['c'] ?? 0)) - 2;
            if (Validators::isId($class + 1)) {
                $userQuery->where('u.class', $class);
                $q = self::appendQueryParam($q, 'c='.($class + 2));
            }

            // IP
            $ip = trim((string) ($params['ip'] ?? ''));
            if ($ip) {
                if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                    throw new InvalidArgumentException('Bad IP.');
                }
                $mask = trim((string) ($params['ma'] ?? ''));
                if ($mask == '' || $mask == '255.255.255.255') {
                    $userQuery->where('u.ip', $ip);
                } else {
                    if (substr($mask, 0, 1) == '/') {
                        $cidr = substr($mask, 1);
                        if (! is_numeric($cidr) || $cidr < 0 || $cidr > 32) {
                            throw new InvalidArgumentException('Bad subnet mask.');
                        }
                        $mask = long2ip((int) (pow(2, 32) - pow(2, 32 - (int) $cidr)));
                    } elseif (! filter_var($mask, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        throw new InvalidArgumentException('Bad subnet mask.');
                    }
                    $userQuery->whereRaw('INET_ATON(u.ip) & INET_ATON(?) = INET_ATON(?) & INET_ATON(?)', [$mask, $ip, $mask]);
                    $q = self::appendQueryParam($q, 'ma='.$mask);
                }
                $q = self::appendQueryParam($q, 'ip='.$ip);
            }

            // ratio
            $ratio = trim((string) ($params['r'] ?? ''));
            if ($ratio) {
                if ($ratio == '---') {
                    $userQuery->where('u.uploaded', 0)->where('u.downloaded', 0);
                } elseif (strtolower(substr($ratio, 0, 3)) == 'inf') {
                    $userQuery->where('u.uploaded', '>', 0)->where('u.downloaded', 0);
                } else {
                    if (! is_numeric($ratio) || $ratio < 0) {
                        throw new InvalidArgumentException('Bad ratio.');
                    }
                    $ratiotype = (string) ($params['rt'] ?? '');
                    $q = self::appendQueryParam($q, 'rt='.$ratiotype);
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
                        $q = self::appendQueryParam($q, 'r2='.$ratio2);
                    } elseif ($ratiotype == '2') {
                        $userQuery->whereRaw('(u.uploaded/u.downloaded) < ?', [(float) $ratio]);
                    } elseif ($ratiotype == '1') {
                        $userQuery->whereRaw('(u.uploaded/u.downloaded) > ?', [(float) $ratio]);
                    } else {
                        $userQuery->whereRaw('(u.uploaded/u.downloaded) BETWEEN ? AND ?', [max(0, (float) $ratio - 0.004), (float) $ratio + 0.004]);
                    }
                }
                $q = self::appendQueryParam($q, 'r='.$ratio);
            }

            // comment
            $co = trim((string) ($params['co'] ?? ''));
            if ($co !== '' && $hasModcomment) {
                $comments = explode(' ', $co);
                $comments_inc = [];
                $comments_exc = [];
                foreach ($comments as $comment) {
                    if (substr($comment, 0, 1) == '~') {
                        if ($comment == '~') {
                            continue;
                        }
                        $comments_exc[] = substr($comment, 1);
                    } else {
                        $comments_inc[] = $comment;
                    }
                }
                if (! empty($comments_inc)) {
                    $userQuery->where(function ($query) use ($comments_inc) {
                        $first = true;
                        foreach ($comments_inc as $comment) {
                            if (! self::hasWildcard($comment)) {
                                $method = $first ? 'where' : 'orWhere';
                                $query->$method('u.modcomment', 'like', '%'.$comment.'%');
                            } else {
                                $comment = str_replace(['?', '*'], ['_', '%'], $comment);
                                $method = $first ? 'where' : 'orWhere';
                                $query->$method('u.modcomment', 'like', $comment);
                            }
                            $first = false;
                        }
                    });
                }
                if (! empty($comments_exc)) {
                    $userQuery->where(function ($query) use ($comments_exc) {
                        foreach ($comments_exc as $comment) {
                            if (! self::hasWildcard($comment)) {
                                $query->where('u.modcomment', 'not like', '%'.$comment.'%');
                            } else {
                                $comment = str_replace(['?', '*'], ['_', '%'], $comment);
                                $query->where('u.modcomment', 'not like', $comment);
                            }
                        }
                    });
                }
                $q = self::appendQueryParam($q, 'co='.rawurlencode($co));
            }

            // uploaded
            $ul = trim((string) ($params['ul'] ?? ''));
            if ($ul) {
                if (! is_numeric($ul) || $ul < 0) {
                    throw new InvalidArgumentException('Bad uploaded amount.');
                }
                $ultype = (string) ($params['ult'] ?? '');
                $q = self::appendQueryParam($q, 'ult='.$ultype);
                if ($ultype == '3') {
                    $ul2 = trim((string) ($params['ul2'] ?? ''));
                    if ($ul2 == '') {
                        throw new InvalidArgumentException('Two uploaded amounts needed for this type of search.');
                    }
                    if (! is_numeric($ul2) || $ul2 < $ul) {
                        throw new InvalidArgumentException('Bad second uploaded amount.');
                    }
                    $userQuery->whereBetween('u.uploaded', [(float) $ul * $unit, (float) $ul2 * $unit]);
                    $q = self::appendQueryParam($q, 'ul2='.$ul2);
                } elseif ($ultype == '2') {
                    $userQuery->where('u.uploaded', '<', (float) $ul * $unit);
                } elseif ($ultype == '1') {
                    $userQuery->where('u.uploaded', '>', (float) $ul * $unit);
                } else {
                    $userQuery->whereBetween('u.uploaded', [max(0, ((float) $ul - 0.004) * $unit), ((float) $ul + 0.004) * $unit]);
                }
                $q = self::appendQueryParam($q, 'ul='.$ul);
            }

            // downloaded
            $dl = trim((string) ($params['dl'] ?? ''));
            if ($dl) {
                if (! is_numeric($dl) || $dl < 0) {
                    throw new InvalidArgumentException('Bad downloaded amount.');
                }
                $dltype = (string) ($params['dlt'] ?? '');
                $q = self::appendQueryParam($q, 'dlt='.$dltype);
                if ($dltype == '3') {
                    $dl2 = trim((string) ($params['dl2'] ?? ''));
                    if ($dl2 == '') {
                        throw new InvalidArgumentException('Two downloaded amounts needed for this type of search.');
                    }
                    if (! is_numeric($dl2) || $dl2 < $dl) {
                        throw new InvalidArgumentException('Bad second downloaded amount.');
                    }
                    $userQuery->whereBetween('u.downloaded', [(float) $dl * $unit, (float) $dl2 * $unit]);
                    $q = self::appendQueryParam($q, 'dl2='.$dl2);
                } elseif ($dltype == '2') {
                    $userQuery->where('u.downloaded', '<', (float) $dl * $unit);
                } elseif ($dltype == '1') {
                    $userQuery->where('u.downloaded', '>', (float) $dl * $unit);
                } else {
                    $userQuery->whereBetween('u.downloaded', [max(0, ((float) $dl - 0.004) * $unit), ((float) $dl + 0.004) * $unit]);
                }
                $q = self::appendQueryParam($q, 'dl='.$dl);
            }

            // date joined
            $d = trim((string) ($params['d'] ?? ''));
            if ($d) {
                $date = self::parseDate($d);
                if ($date === null) {
                    throw new InvalidArgumentException('Invalid date.');
                }
                $q = self::appendQueryParam($q, 'd='.$date);
                $datetype = (string) ($params['dt'] ?? '');
                $q = self::appendQueryParam($q, 'dt='.$datetype);
                if ($datetype == '0') {
                    $userQuery->whereBetween('u.added', [$date, date('Y-m-d H:i:s', strtotime($date) + 86400)]);
                } else {
                    if ($datetype == '3') {
                        $d2 = trim((string) ($params['d2'] ?? ''));
                        $date2 = self::parseDate($d2);
                        if ($date2 !== null) {
                            $q = self::appendQueryParam($q, 'd2='.$date2);
                            $userQuery->whereBetween('u.added', [$date, $date2]);
                        } else {
                            throw new InvalidArgumentException('Two dates needed for this type of search.');
                        }
                    } elseif ($datetype == '1') {
                        $userQuery->where('u.added', '<', $date);
                    } elseif ($datetype == '2') {
                        $userQuery->where('u.added', '>', $date);
                    }
                }
            }

            // date last seen
            $ls = trim((string) ($params['ls'] ?? ''));
            if ($ls) {
                $last = self::parseDate($ls);
                if ($last === null) {
                    throw new InvalidArgumentException('Invalid date.');
                }
                $q = self::appendQueryParam($q, 'ls='.$last);
                $lasttype = (string) ($params['lst'] ?? '');
                $q = self::appendQueryParam($q, 'lst='.$lasttype);
                if ($lasttype == '0') {
                    $userQuery->whereBetween('u.last_access', [$last, date('Y-m-d H:i:s', strtotime($last) + 86400)]);
                } else {
                    if ($lasttype == '3') {
                        $ls2 = trim((string) ($params['ls2'] ?? ''));
                        $last2 = self::parseDate($ls2);
                        if ($last2 !== null) {
                            $q = self::appendQueryParam($q, 'ls2='.$last2);
                            $userQuery->whereBetween('u.last_access', [$last, $last2]);
                        } else {
                            throw new InvalidArgumentException('The second date is not valid.');
                        }
                    } elseif ($lasttype == '1') {
                        $userQuery->where('u.last_access', '<', $last);
                    } elseif ($lasttype == '2') {
                        $userQuery->where('u.last_access', '>', $last);
                    }
                }
            }

            // status
            $status = (string) ($params['st'] ?? '');
            if ($status) {
                $userQuery->where('u.status', $status == '1' ? 'confirmed' : 'pending');
                $q = self::appendQueryParam($q, 'st='.$status);
            }

            // account status
            $accountstatus = (string) ($params['as'] ?? '');
            if ($accountstatus) {
                $userQuery->where('u.enabled', $accountstatus == '1' ? 'yes' : 'no');
                $q = self::appendQueryParam($q, 'as='.$accountstatus);
            }

            // donor
            $donor = (string) ($params['do'] ?? '');
            if ($donor) {
                $userQuery->where('u.donor', $donor == '1' ? 'yes' : 'no');
                $q = self::appendQueryParam($q, 'do='.$donor);
            }

            // warned
            $warned = (string) ($params['w'] ?? '');
            if ($warned) {
                $userQuery->where('u.warned', $warned == '1' ? 'yes' : 'no');
                $q = self::appendQueryParam($q, 'w='.$warned);
            }

            // disabled IP
            $disabled = (string) ($params['dip'] ?? '');
            if ($disabled) {
                $userQuery->leftJoin('users as u2', 'u.ip', '=', 'u2.ip')->where('u2.enabled', 'no');
                $q = self::appendQueryParam($q, 'dip='.$disabled);
            }

            // active
            $active = (string) ($params['ac'] ?? '');
            if ($active == '1') {
                $userQuery->leftJoin('peers as p', 'u.id', '=', 'p.userid');
                $q = self::appendQueryParam($q, 'ac='.$active);
            }
        }

        $select_is = 'u.id, u.username, u.email, u.status, u.added, u.last_access, u.ip,
	u.class, u.uploaded, u.downloaded, u.donor, u.enabled, u.warned';
        if ($hasModcomment) {
            $select_is = str_replace('u.donor, u.enabled', 'u.donor, u.modcomment, u.enabled', $select_is);
        }

        $count = (int) (clone $userQuery)->selectRaw('count(distinct u.id) as count')->value('count');

        $page = (int) ($params['page'] ?? 0);
        if ($page < 0) {
            $page = 0;
        }
        $offset = $page * $perPage;

        $rows = (clone $userQuery)->distinct()->selectRaw($select_is)->offset($offset)->limit($perPage)->get()->map(fn ($row) => (array) $row)->all(); // @phpstan-ignore argument.type

        $q = $q !== '' ? $q.'&' : '';

        return [
            'count' => $count,
            'rows' => $rows,
            'q' => $q,
        ];
    }

    private static function appendQueryParam(string $q, string $param): string
    {
        return $q !== '' ? $q.'&'.$param : $param;
    }

    private static function hasWildcard(string $text): bool
    {
        return str_contains($text, '*')
            || str_contains($text, '?')
            || str_contains($text, '%')
            || str_contains($text, '_');
    }

    private static function parseDate(string $date): ?string
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
