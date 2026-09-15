<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\UserStatus;
use App\Support\Validators;
use Illuminate\Contracts\Database\Query\Builder;
use InvalidArgumentException;

/**
 * Scalar/flag where-clauses for the administrative user search
 * (class, IP, status, enabled, donor, warned, disabled-IP join, active peers join).
 */
final class UserSearchAttributeFilters
{
    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyClass(Builder $userQuery, array $params): array
    {
        $class = ((int) ($params['c'] ?? 0)) - 2;
        if (! Validators::isId($class + 1)) {
            return [];
        }
        $userQuery->where('u.class', $class);

        return ['c='.($class + 2)];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyIp(Builder $userQuery, array $params): array
    {
        $ip = trim((string) ($params['ip'] ?? ''));
        if (! $ip) {
            return [];
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('Bad IP.');
        }
        $fragments = [];
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
            $fragments[] = 'ma='.$mask;
        }
        $fragments[] = 'ip='.$ip;

        return $fragments;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyStatus(Builder $userQuery, array $params): array
    {
        $status = (string) ($params['st'] ?? '');
        if (! $status) {
            return [];
        }
        $userQuery->where('u.status', $status == '1' ? UserStatus::CONFIRMED->value : UserStatus::PENDING->value);

        return ['st='.$status];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyAccountStatus(Builder $userQuery, array $params): array
    {
        $accountstatus = (string) ($params['as'] ?? '');
        if (! $accountstatus) {
            return [];
        }
        $userQuery->where('u.enabled', $accountstatus == '1');

        return ['as='.$accountstatus];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyDonor(Builder $userQuery, array $params): array
    {
        $donor = (string) ($params['do'] ?? '');
        if (! $donor) {
            return [];
        }
        $userQuery->where('u.donor', $donor == '1');

        return ['do='.$donor];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyWarned(Builder $userQuery, array $params): array
    {
        $warned = (string) ($params['w'] ?? '');
        if (! $warned) {
            return [];
        }
        $userQuery->where('u.warned', $warned == '1');

        return ['w='.$warned];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyDisabledIp(Builder $userQuery, array $params): array
    {
        $disabled = (string) ($params['dip'] ?? '');
        if (! $disabled) {
            return [];
        }
        $userQuery->leftJoin('users as u2', 'u.ip', '=', 'u2.ip')->where('u2.enabled', false);

        return ['dip='.$disabled];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function applyActive(Builder $userQuery, array $params): array
    {
        $active = (string) ($params['ac'] ?? '');
        if ($active != '1') {
            return [];
        }
        $userQuery->leftJoin('peers as p', 'u.id', '=', 'p.userid');

        return ['ac='.$active];
    }
}
