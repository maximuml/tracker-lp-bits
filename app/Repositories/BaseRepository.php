<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Environment;
use App\Support\Locale;
use App\Support\Logger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BaseRepository
{
    /**
     * @param  array<int|string, mixed>  $params
     * @return array<int|string, mixed>
     */
    protected function getSortFieldAndType(array $params): array
    {
        $field = ! empty($params['sort_field']) ? $params['sort_field'] : 'id';
        $type = 'desc';
        if (! empty($params['sort_type']) && Str::startsWith($params['sort_type'], 'asc')) {
            $type = 'asc';
        }

        return [$field, $type];
    }

    /**
     * @return mixed
     */
    protected function getPerPageFromRequest(Request $request)
    {
        $perPage = $request->get('per_page');
        if ($perPage && $perPage > 100) {
            Logger::writeWithContext((string) "per_page: {$perPage} > 100", (string) 'warning', (bool) false);
            $perPage = 100;
        }

        return $perPage;
    }

    /**
     * @param  mixed  $username
     * @param  mixed  $user
     * @return mixed
     */
    protected function handleAnonymous($username, $user, User $authenticator, ?Torrent $torrent = null)
    {
        if (! $user) {
            return '';
        }
        if ($user->privacy == 'strong' || ($torrent && $torrent->anonymous == 1 && $user->id == $torrent->owner)) {
            // 用户强私密，或者种子作者匿名而当前项作者刚好为种子作者
            $anonymousText = Locale::trans('label.anonymous', [], null);
            if (Permission::can(PermissionEnum::VIEW_ANONYMOUS, $authenticator) || $user->id == $authenticator->id) {
                // 但当前用户权限可以查看匿名者，或当前用户查看自己的数据，显示个匿名，后边加真实用户名
                return sprintf('%s(%s)', $anonymousText, $username);
            } else {
                return $anonymousText;
            }
        } else {
            return $username;
        }
    }

    /**
     * @param  mixed  $user
     * @param  mixed  $fields
     */
    protected function getUser($user, $fields = null): ?User
    {
        if ($user === null) {
            return null;
        }
        if ($user instanceof User) {
            return $user;
        }
        if ($fields === null) {
            $fields = User::$commonFields;
        }

        return User::query()->findOrFail(intval($user), $fields);
    }

    /**
     * @param  mixed  $command
     * @param  mixed  $format
     * @return string|array<int|string, mixed>
     */
    protected function executeCommand($command, $format = 'string'): string|array
    {
        return Environment::run($command, $format, (bool) false, (bool) true);
    }
}
