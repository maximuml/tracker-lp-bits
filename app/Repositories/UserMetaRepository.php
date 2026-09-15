<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\UsernameChangeType;
use App\Models\Message;
use App\Models\User;
use App\Models\UserMeta;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * User meta ("props") repository: listing meta records, granting them, and
 * consuming benefit cards such as the change-username card.
 * Extracted from UserRepository to keep both classes under the 400-line ratchet.
 */
class UserMetaRepository extends BaseRepository
{
    /**
     * @param  mixed  $uid
     * @param  mixed  $metaKeys
     * @param  mixed  $valid
     * @return mixed
     */
    public function listMetas($uid, $metaKeys = [], $valid = true)
    {
        $query = UserMeta::query()->where('uid', $uid);
        if (! empty($metaKeys)) {
            $query->whereIn('meta_key', Arr::wrap($metaKeys));
        }
        if ($valid) {
            $query->where('status', 0)->where(function (Builder $query) {
                $query->whereNull('deadline')->orWhere('deadline', '>=', now());
            });
        }

        return $query->get()->groupBy('meta_key');
    }

    /**
     * @param  mixed  $uid
     * @param  array<int|string, mixed>  $params
     */
    public function consumeBenefit($uid, array $params): bool
    {
        $metaKey = $params['meta_key'];
        $records = $this->listMetas($uid, $metaKey);
        if (! $records->has($metaKey)) {
            throw new \RuntimeException("User do not has this metaKey: $metaKey");
        }
        /** @var UserMeta $meta */
        $meta = $records->get($metaKey)->first();
        $user = User::query()->findOrFail((int) $uid, User::$commonFields);
        if ($metaKey == UserMeta::META_KEY_CHANGE_USERNAME) {
            $changeLog = $user->usernameChangeLogs()->orderBy('id', 'desc')->first();
            if ($changeLog && $changeLog->created_at !== null) {
                $miniDays = SiteConfig::current()->system->changeUsernameMinIntervalInDays(365);
                if (abs($changeLog->created_at->diffInDays()) <= $miniDays) {
                    $msg = Locale::trans('user.change_username_lte_min_interval', ['last_change_time' => $changeLog->created_at, 'interval' => $miniDays], null);
                    throw new \RuntimeException($msg);
                }
            }
            DB::transaction(function () use ($user, $meta, $params) {
                $this->changeUsername(
                    $user, UsernameChangeType::USER->value, $user, $params['username'],
                    SiteConfig::current()->system->changeUsernameCardAllowCharactersOutsideTheAlphabets()
                );
                $meta->delete();
                Cache::clearUser($user->id, (string) $user->passkey);
            });

            return true;
        }

        throw new \InvalidArgumentException("Invalid meta_key: $metaKey");
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $changeType
     * @param  mixed  $targetUser
     * @param  mixed  $newUsername
     * @param  mixed  $allowOutsideAlphabets
     */
    private function changeUsername($operator, $changeType, $targetUser, $newUsername, $allowOutsideAlphabets = false): bool
    {
        $operator = $this->getUser($operator);
        $targetUser = $this->getUser($targetUser);
        if ($operator === null || $targetUser === null) {
            throw new \InvalidArgumentException('Operator or target user not found');
        }
        $this->checkPermission($operator, $targetUser);
        if ($targetUser->username == $newUsername) {
            throw new \RuntimeException('New username can not be the same with current username !');
        }
        $strWidth = mb_strwidth($newUsername);
        if ($strWidth < 4 || $strWidth > 20) {
            throw new \InvalidArgumentException('Invalid username, maybe too long or too short');
        }
        if (! $allowOutsideAlphabets && ! Validators::isUsername($newUsername)) {
            throw new \InvalidArgumentException('Invalid username, only support alphabets');
        }
        if (User::query()->where('username', $newUsername)->where('id', '!=', $targetUser->id)->exists()) {
            throw new \RuntimeException("Username: $newUsername already exists !");
        }
        $changeLog = [
            'uid' => $targetUser->id,
            'operator' => $operator->username,
            'change_type' => $changeType,
            'username_old' => $targetUser->username,
            'username_new' => $newUsername,
        ];
        DB::transaction(function () use ($targetUser, $changeLog) {
            $targetUser->usernameChangeLogs()->create($changeLog);
            $targetUser->username = $changeLog['username_new'];
            $targetUser->save();
        });
        $this->clearCache($targetUser);

        return true;
    }

    /**
     * @param  mixed  $user
     * @param  array<string, mixed>  $metaData
     * @param  array<string, mixed>  $keyExistsUpdates
     * @param  mixed  $notify
     * @return mixed
     */
    public function addMeta($user, array $metaData, array $keyExistsUpdates = [], $notify = true)
    {
        $user = $this->getUser($user);
        if ($user === null) {
            throw new \InvalidArgumentException('User not found');
        }
        $locale = $user->locale;
        $metaKey = $metaData['meta_key'];
        $metaName = Locale::trans("label.user_meta.meta_keys.{$metaKey}", [], $locale);
        $allowMultiple = UserMeta::$metaKeys[$metaKey]['multiple'];
        $log = "user: {$user->id}, locale: $locale, metaKey: $metaKey, allowMultiple: $allowMultiple";
        $message = [
            'receiver' => $user->id,
            'added' => now(),
            'subject' => Locale::trans('user.grant_props_notification.subject', ['name' => $metaName], $locale),
        ];
        if (! empty($keyExistsUpdates['duration']) && $metaKey != UserMeta::META_KEY_CHANGE_USERNAME) {
            $durationText = $keyExistsUpdates['duration'].' Days';
        } else {
            $durationText = Locale::trans('label.permanent', [], $locale);
        }
        $operatorId = UserDisplay::currentId();
        $operatorInfo = UserDisplay::row($operatorId);
        $operatorName = is_array($operatorInfo) ? (string) ($operatorInfo['username'] ?? '') : '';
        $message['msg'] = Locale::trans('user.grant_props_notification.body', ['name' => $metaName, 'operator' => $operatorName, 'duration' => $durationText], $locale);
        if (! empty($metaData['duration'])) {
            $metaData['deadline'] = now()->addDays((int) $metaData['duration']);
        }
        if ($allowMultiple) {
            // Allow multiple, just insert
            $result = $user->metas()->create($metaData);
            $log .= ', allowMultiple, just insert';
        } else {
            $metaExists = $user->metas()->where('meta_key', $metaKey)->first();
            $log .= ', metaExists: '.($metaExists->id ?? '');
            if (! $metaExists) {
                $result = $user->metas()->create($metaData);
                $log .= ', meta not exists, just create';
            } else {
                $log .= ', meta exists';
                $keyExistsUpdates['updated_at'] = now();
                if (! empty($keyExistsUpdates['duration'])) {
                    if ($metaExists->deadline === null) {
                        throw new \RuntimeException(Locale::trans('user.metas.already_valid_forever', ['meta_key_text' => $metaExists->metaKeyText], null));
                    }
                    $log .= ", has duration: {$keyExistsUpdates['duration']}";
                    if ($metaExists->deadline && $metaExists->deadline->gte(now())) {
                        $log .= ', not expire';
                        $keyExistsUpdates['deadline'] = $metaExists->deadline->addDays((int) $keyExistsUpdates['duration']);
                    } else {
                        $log .= ', expired or not set';
                        $keyExistsUpdates['deadline'] = now()->addDays((int) $keyExistsUpdates['duration']);
                    }
                    unset($keyExistsUpdates['duration']);
                } else {
                    $keyExistsUpdates['deadline'] = null;
                }
                $log .= ', update: '.json_encode($keyExistsUpdates);
                $result = $metaExists->update($keyExistsUpdates);
            }
        }
        if ($result) {
            $this->clearCache($user);
            if ($notify) {
                Message::add($message);
            }
        }
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        return $result;
    }

    /**
     * @return mixed
     */
    private function clearCache(User $user)
    {
        Cache::clearUser($user->id, (string) $user->passkey);
    }
}
