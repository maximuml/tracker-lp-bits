<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\UserClass as UserClassEnum;
use App\Enums\UsernameChangeType;
use App\Enums\UserStatus;
use App\Exceptions\InsufficientPermissionException;
use App\Http\Resources\UserResource;
use App\Models\LoginLog;
use App\Models\Message;
use App\Models\User;
use App\Models\UserMeta;
use App\Models\UserModifyLog;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Email;
use App\Support\Environment;
use App\Support\Events;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\Network;
use App\Support\PasswordHasher;
use App\Support\Security\PasskeyGenerator;
use App\Support\Token;
use App\Support\UserDisplay;
use App\Support\Validators;
use App\Utils\ApiQueryBuilder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * User repository: listing, detail, CRUD, meta, and auth helpers.
 *
 * Moderation and administration logic has been extracted to:
 *
 * @see UserModerationRepository
 */
class UserRepository extends BaseRepository
{
    public function __construct(
        private readonly UserModerationRepository $userModerationRepository,
    ) {
        //
    }

    /** @var array<int, string> */
    private static array $allowIncludes = ['inviter', 'valid_medals'];

    /** @var array<int, string> */
    private static array $allowIncludeFields = ['seeding_leeching_data'];

    /** @var array<int, string> */
    private static array $allowIncludeCounts = [];

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function getList(array $params)
    {
        $query = User::query();
        if (! empty($params['id'])) {
            $query->where('id', $params['id']);
        }
        if (! empty($params['username'])) {
            $query->where('username', 'like', "%{$params['username']}%");
        }
        if (! empty($params['email'])) {
            $query->where('email', 'like', "%{$params['email']}%");
        }
        if (isset($params['class']) && $params['class'] !== '') {
            $query->where('class', $params['class']);
        }
        [$sortField, $sortType] = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);

        return $query->paginate();
    }

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function getBase($id)
    {
        $user = User::query()->findOrFail((int) $id, ['id', 'username', 'email', 'avatar']);

        return $user;
    }

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function getDetail($id, Authenticatable $currentUser)
    {
        // query this info default
        $query = User::query()->with([]);
        $apiQueryBuilder = ApiQueryBuilder::for(UserResource::NAME, $query)
            ->allowIncludes(self::$allowIncludes)
            ->allowIncludeCounts(self::$allowIncludeCounts)
            ->allowIncludeFields(self::$allowIncludeFields);
        $query = $apiQueryBuilder->build();
        $user = $query->findOrFail((int) $id);
        Gate::authorize('view', $user);
        $userList = $this->appendIncludeFields($apiQueryBuilder, $currentUser, [$user]);

        return $userList[0];
    }

    /**
     * @param  mixed  $userList
     * @return mixed
     */
    private function appendIncludeFields(ApiQueryBuilder $apiQueryBuilder, Authenticatable $currentUser, $userList)
    {
        $idArr = [];
        foreach ($userList as $user) {
            $idArr[] = $user->id;
        }
        if ($hasFieldSeedingData = $apiQueryBuilder->hasIncludeField('seeding_leeching_data')) {
            $seedingData = $this->listUserSeedingLeechingData($idArr);
        }
        foreach ($userList as $user) {
            $id = $user->id;
            if ($hasFieldSeedingData && isset($seedingData[$id])) {
                $user->seeding_leeching_data = $seedingData[$id];
            }
        }

        return $userList;
    }

    /**
     * create user
     *
     * @param  array<int|string, mixed>  $params
     * @return User
     */
    public function store(array $params)
    {
        $password = $params['password'];
        if ($password != $params['password_confirmation']) {
            throw new \InvalidArgumentException('password confirmation != password');
        }
        $username = $params['username'];
        if (! Validators::isUsername($username)) {
            throw new \InvalidArgumentException("Invalid username: $username");
        }
        $email = htmlspecialchars(trim($params['email']));
        $email = Email::sanitizeForDisplay((string) $email);
        if (! Email::isWellFormed((string) $email)) {
            throw new \InvalidArgumentException("Invalid email: $email");
        }
        if (User::query()->where('email', $email)->exists()) {
            throw new \InvalidArgumentException("The email address: $email is already in use");
        }
        if (User::query()->where('username', $username)->exists()) {
            throw new \InvalidArgumentException("The username: $username is already in use");
        }
        if (mb_strlen($password) < 6 || mb_strlen($password) > 40) {
            throw new \InvalidArgumentException("Invalid password: $password, it should be more than 6 character and less than 40 character");
        }
        if (! empty($params['class'])) {
            $class = intval($params['class']);
            if (! IN_NEXUS) {
                $authUser = Auth::user();
                if ($authUser && $class >= $authUser->class) {
                    throw new InsufficientPermissionException('No permission');
                }
            }
        } else {
            $class = UserClassEnum::USER->value;
        }

        if (! isset(User::$classes[$class])) {
            throw new \InvalidArgumentException("Invalid user class: $class");
        }
        $setting = SiteConfig::current()->main->toArray();
        $secret = Token::randomHex((int) 20);
        $passhash = PasswordHasher::hash($password);
        $data = [
            'username' => $username,
            'email' => $email,
            'secret' => $secret,
            'passhash_algo' => PasswordHasher::ALGO_ARGON2ID,
            'auth_key' => Token::randomHex((int) 20),
            'editsecret' => '',
            'passhash' => $passhash,
            'stylesheet' => $setting['defstylesheet'],
            'added' => now()->toDateTimeString(),
            'status' => UserStatus::CONFIRMED->value,
            'class' => $class,
            'passkey' => app(PasskeyGenerator::class)->generate(),
        ];
        $user = new User($data);
        if (! empty($params['id'])) {
            if (User::query()->where('id', $params['id'])->exists()) {
                throw new \InvalidArgumentException("uid: {$params['id']} already exists.");
            }
            Logger::writeWithContext((string) ('[CREATE_USER], specific id: '.$params['id']), (string) 'info', (bool) false);
            $user->id = $params['id'];
        }
        $user->save();
        Events::fire('user_created', $user, null);

        return $user;
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $password
     * @param  mixed  $passwordConfirmation
     * @return mixed
     */
    public function resetPassword($id, $password, $passwordConfirmation)
    {
        if ($password != $passwordConfirmation) {
            throw new \InvalidArgumentException('password confirmation != password');
        }
        $user = User::query()->findOrFail((int) $id, ['id', 'username', 'class']);
        $operator = UserDisplay::currentId();
        if ($operator) {
            $this->checkPermission($operator, $user);
        }
        $secret = Token::randomHex((int) 20);
        $passhash = PasswordHasher::hash($password);
        $update = [
            'secret' => $secret,
            'passhash' => $passhash,
            'passhash_algo' => PasswordHasher::ALGO_ARGON2ID,
            'auth_key' => Token::randomHex((int) 20),
        ];
        $user->update($update);

        return true;
    }

    /**
     * @param  mixed  $id
     * @return mixed
     */
    public function getInviteInfo($id)
    {
        $user = User::query()->findOrFail((int) $id, ['id']);

        return $user->invitee_code()->with('inviter_user')->first();
    }

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
    public function saveLoginLog(int $uid, string $ip, string $client = '', bool $notify = false)
    {
        $locationInfo = Network::geoIpInfo($ip) ?: [];
        $loginLog = LoginLog::query()->create([
            'ip' => $ip,
            'uid' => $uid,
            'country' => $locationInfo['country_en'] ?? '',
            'city' => $locationInfo['city_en'] ?? '',
            'client' => $client,
        ]);
        if ($notify) {
            $command = sprintf('user:login_notify --this_id=%s', $loginLog->id);
            Logger::writeWithContext((string) "[LOGIN_NOTIFY], user: {$uid}, {$command}", (string) 'info', (bool) false);
            Environment::run($command, 'string', (bool) true, (bool) false);
        }

        return $loginLog;
    }

    /**
     * get user seeding/leeching count and size
     *
     * @param  array<int|string, mixed>  $userIdArr
     * @return array<int|string, mixed>
     *
     * @see calculate_seed_bonus()
     */
    private function listUserSeedingLeechingData(array $userIdArr)
    {
        $minSize = SiteConfig::current()->bonus->minSize(0);
        $data = DB::table('torrents')
            ->leftJoin('peers', 'peers.torrent', '=', 'torrents.id')
            ->select('peers.userid', 'peers.seeder', 'torrents.size')
            ->whereIn('peers.userid', $userIdArr)
            ->where('torrents.size', '>', $minSize)
            ->groupBy('peers.torrent', 'peers.peer_id', 'peers.userid', 'peers.seeder')
            ->get();
        $result = [];
        foreach ($data as $row) {
            $row = (array) $row;
            if (! isset($result[$row['userid']])) {
                $result[$row['userid']] = [
                    'seeding_count' => 0,
                    'seeding_size' => 0,
                    'leeching_count' => 0,
                    'leeching_size' => 0,
                ];
            }
            if ($row['seeder'] == 1) {
                $result[$row['userid']]['seeding_count'] += 1;
                $result[$row['userid']]['seeding_size'] += $row['size'];
            } else {
                $result[$row['userid']]['leeching_count'] += 1;
                $result[$row['userid']]['leeching_size'] += $row['size'];
            }
        }

        return $result;
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $minAuthClass
     * @return void
     */
    private function checkPermission($operator, User $user, $minAuthClass = 'authority.prfmanage')
    {
        $operator = $this->getUser($operator);
        if ($operator === null) {
            throw new \RuntimeException('Operator not found');
        }
        if ($operator->id == $user->id) {
            return;
        }
        $permissionName = str_starts_with($minAuthClass, 'authority.')
            ? substr($minAuthClass, strlen('authority.'))
            : $minAuthClass;
        $classRequire = SiteConfig::current()->authority->permission($permissionName);
        if ($classRequire === null || $operator->class < $classRequire || $operator->class <= $user->class) {
            throw new InsufficientPermissionException;
        }
    }

    /**
     * @return mixed
     */
    private function clearCache(User $user)
    {
        Cache::clearUser($user->id, (string) $user->passkey);
    }

    /**
     * Find a user by id with the common field subset used by cache clearing.
     *
     * @return User|null
     */
    public function findForCacheClear(int|string $id)
    {
        return User::query()->find($id, User::$commonFields);
    }

    public function findForDisplay(int|string $id): ?User
    {
        $neededColumns = [
            'id', 'class', 'enabled', 'privacy', 'avatar', 'signature', 'uploaded', 'downloaded',
            'last_access', 'username', 'donor', 'donoruntil', 'leechwarn', 'warned', 'title',
            'downloadpos', 'parked', 'clientselect', 'showclienterror',
        ];

        return User::query()
            ->with([
                'wearing_medals' => function ($query) {
                    $query->orderBy('user_medals.priority', 'desc')
                        ->orderBy('user_medals.id', 'desc')
                        ->limit((int) SiteConfig::current()->system->maximumNumberOfMedalsCanBeWorn(3));
                },
            ])
            ->find($id, $neededColumns);
    }

    public function logModify(int|string $userId, string $comment): void
    {
        UserModifyLog::query()->create([
            'user_id' => $userId,
            'content' => $comment,
        ]);
    }

    /**
     * @param  list<int>  $ids
     * @param  list<string>  $columns
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function getByIds(array $ids, array $columns = ['*']): \Illuminate\Database\Eloquent\Collection
    {
        return User::query()->find($ids, $columns)->keyBy('id');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Delegating methods — backward compatibility for callers not yet updated
    //  to use UserModerationRepository directly.
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param  mixed  $uid
     * @param  mixed  $reason
     * @return mixed
     */
    public function disableUser(User $operator, $uid, $reason = '')
    {
        return $this->userModerationRepository->disableUser($operator, $uid, $reason);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $reason
     * @return mixed
     */
    public function enableUser(User $operator, $uid, $reason = '')
    {
        return $this->userModerationRepository->enableUser($operator, $uid, $reason);
    }

    /**
     * @return mixed
     */
    public function getModComment(int $id)
    {
        return $this->userModerationRepository->getModComment($id);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $action
     * @param  mixed  $field
     * @param  mixed  $value
     * @param  mixed  $reason
     */
    public function incrementDecrement(User $operator, $uid, $action, $field, $value, $reason = ''): bool
    {
        return $this->userModerationRepository->incrementDecrement($operator, $uid, $action, $field, $value, $reason);
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $uid
     */
    public function removeLeechWarn($operator, $uid): bool
    {
        return $this->userModerationRepository->removeLeechWarn($operator, $uid);
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $uid
     */
    public function removeTwoStepAuthentication($operator, $uid): bool
    {
        return $this->userModerationRepository->removeTwoStepAuthentication($operator, $uid);
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $user
     * @param  mixed  $disableReasonKey
     * @return mixed
     */
    public function updateDownloadPrivileges($operator, $user, bool $status, $disableReasonKey = null)
    {
        return $this->userModerationRepository->updateDownloadPrivileges($operator, $user, $status, $disableReasonKey);
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $user
     * @return mixed
     */
    public function updateUploadPrivileges($operator, $user, bool $status)
    {
        return $this->userModerationRepository->updateUploadPrivileges($operator, $user, $status);
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $user
     * @return mixed
     */
    public function updateForumPost($operator, $user, bool $status)
    {
        return $this->userModerationRepository->updateForumPost($operator, $user, $status);
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $user
     * @param  int  $weeks  0 = remove warning, 255 = indefinite
     * @param  string  $reason  PM reason text
     * @return mixed
     */
    public function warnUser($operator, $user, int $weeks, string $reason = '')
    {
        return $this->userModerationRepository->warnUser($operator, $user, $weeks, $reason);
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $targetUser
     * @param  mixed  $newClass
     * @param  mixed  $reason
     * @param  array<int|string, mixed>  $extra
     */
    public function changeClass($operator, $targetUser, $newClass, $reason = '', array $extra = []): bool
    {
        return $this->userModerationRepository->changeClass($operator, $targetUser, $newClass, $reason, $extra);
    }

    /** @param  mixed  $id */
    public function confirmUser($id): bool
    {
        return $this->userModerationRepository->confirmUser($id);
    }

    /**
     * @param  array<int>  $userIds
     */
    public function removeWarnings(User $operator, array $userIds): void
    {
        $this->userModerationRepository->removeWarnings($operator, $userIds);
    }

    /**
     * @param  Collection<int, mixed>|int  $id
     * @param  mixed  $reasonKey
     * @return mixed
     */
    public function destroy(Collection|int $id, $reasonKey = 'user.destroy_by_admin')
    {
        return $this->userModerationRepository->destroy($id, $reasonKey);
    }

    /**
     * @return mixed
     */
    public function addTemporaryInvite(?User $operator, int $uid, string $action, int $count, ?int $days, ?string $reason = '')
    {
        return $this->userModerationRepository->addTemporaryInvite($operator, $uid, $action, $count, $days, $reason);
    }

    /**
     * @return mixed
     */
    public function getInviteBtnText(int $uid)
    {
        return $this->userModerationRepository->getInviteBtnText($uid);
    }
}
