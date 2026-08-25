<?php

namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\ModelEventEnum;
use App\Enums\Permission\PermissionEnum;
use App\Exceptions\InsufficientPermissionException;
use App\Exceptions\NexusException;
use App\Http\Resources\UserResource;
use App\Models\Invite;
use App\Models\LoginLog;
use App\Models\Message;
use App\Models\OauthProvider;
use App\Models\Torrent;
use App\Models\User;
use App\Models\UserBanLog;
use App\Models\UserMeta;
use App\Models\UserModifyLog;
use App\Models\UsernameChangeLog;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Email;
use App\Support\Environment;
use App\Support\Events;
use App\Support\Format;
use App\Support\Hooks;
use App\Support\Json;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\Network;
use App\Support\PasswordHasher;
use App\Support\Token;
use App\Support\UserDisplay;
use App\Support\Validators;
use App\Utils\ApiQueryBuilder;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Nexus\Database\NexusDB;

class UserRepository extends BaseRepository
{
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
            $class = User::CLASS_USER;
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
            'status' => User::STATUS_CONFIRMED,
            'class' => $class,
            'passkey' => md5($username.date('Y-m-d H:i:s').$passhash),
        ];
        $user = new User($data);
        if (! empty($params['id'])) {
            if (User::query()->where('id', $params['id'])->exists()) {
                throw new \InvalidArgumentException("uid: {$params['id']} already exists.");
            }
            Logger::writeWithContext((string) ('[CREATE_USER], specific id: '.$params['id']), (string) 'info', (bool) false);
            $user->id = $params['id'];
        }
        if (! empty($params['provider_id'])) {
            if (! OauthProvider::query()->find($params['provider_id'])) {
                throw new \InvalidArgumentException("provider_id: {$params['provider_id']} not exists.");
            }
            Logger::writeWithContext((string) ('[CREATE_USER], specific provider_id: '.$params['provider_id']), (string) 'info', (bool) false);
            $user->provider_id = $params['provider_id'];
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
     * @return array<int|string, mixed>
     *
     * @deprecated  use User::listClass() instead !
     */
    public function listClass()
    {
        return User::listClass();
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $reason
     * @return mixed
     */
    public function disableUser(User $operator, $uid, $reason = '')
    {
        $targetUser = User::query()->findOrFail((int) $uid, ['id', 'enabled', 'username', 'class']);
        if ($targetUser->enabled == User::ENABLED_NO) {
            throw new NexusException('Already disabled !');
        }
        if (empty($reason)) {
            $reason = Locale::trans('user.disable_by_admin', [], null);
        }
        $this->checkPermission($operator, $targetUser);
        $banLog = [
            'uid' => $uid,
            'username' => $targetUser->username,
            'reason' => $reason,
            'operator' => $operator->id,
        ];
        $modCommentText = sprintf('%s - Disable by %s, reason: %s.', now()->format('Y-m-d'), $operator->username, $reason);
        NexusDB::transaction(function () use ($targetUser, $banLog, $modCommentText) {
            $targetUser->updateWithModComment(['enabled' => User::ENABLED_NO], $modCommentText);
            UserBanLog::query()->create($banLog);
        });
        Logger::writeWithContext((string) "user: {$uid}, {$modCommentText}", (string) 'info', (bool) false);
        $this->clearCache($targetUser);
        Events::fire('user_disabled', $targetUser, null);

        return true;
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $reason
     * @return mixed
     */
    public function enableUser(User $operator, $uid, $reason = '')
    {
        $targetUser = User::query()->findOrFail((int) $uid, ['id', 'enabled', 'username', 'class']);
        if ($targetUser->enabled == User::ENABLED_YES) {
            throw new NexusException('Already enabled !');
        }
        $this->checkPermission($operator, $targetUser);
        $update = [
            'enabled' => User::ENABLED_YES,
        ];
        if ($targetUser->class == User::CLASS_PEASANT) {
            // warn users until 30 days
            $until = now()->addDays(30)->toDateTimeString();
            $update['leechwarn'] = 'yes';
            $update['leechwarnuntil'] = $until;
        } else {
            $update['leechwarn'] = 'no';
            $update['leechwarnuntil'] = null;
        }
        $modCommentText = sprintf('%s - Enable by %s, reason: %s', now()->format('Y-m-d'), $operator->username, $reason);
        $targetUser->updateWithModComment($update, $modCommentText);
        Logger::writeWithContext((string) ("user: {$uid}, {$modCommentText}, update: ".Json::encode($update)), (string) 'info', (bool) false);
        $this->clearCache($targetUser);
        Events::fire('user_enabled', $targetUser, null);
        $this->setEnableLatelyCache($targetUser->id);

        return true;
    }

    private function setEnableLatelyCache(int $userId): void
    {
        NexusDB::cache_put(User::getUserEnableLatelyCacheKey($userId), now()->toDateTimeString(), 86400);
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
     * @return mixed
     */
    public function getModComment(int $id)
    {
        $user = User::query()->findOrFail((int) $id, ['modcomment']);

        return $user->modcomment;
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
        $fieldMap = [
            'uploaded' => 'uploaded',
            'downloaded' => 'downloaded',
            'seedbonus' => 'seedbonus',
            'invites' => 'invites',
            'attendance_card' => 'attendance_card',
        ];
        if (! isset($fieldMap[$field])) {
            throw new \InvalidArgumentException("Invalid field: $field, only support: ".implode(', ', array_keys($fieldMap)));
        }
        $sourceField = $fieldMap[$field];
        $targetUser = User::query()->findOrFail((int) $uid, User::$commonFields);
        $this->checkPermission($operator, $targetUser);
        $old = (float) $targetUser->{$sourceField};
        $valueAtomic = (float) $value;
        $formatSize = false;
        if (in_array($field, ['uploaded', 'downloaded'])) {
            // Frontend unit: GB
            $valueAtomic = $valueAtomic * 1024 * 1024 * 1024;
            $formatSize = true;
        }
        if ($action == 'Increment') {
            $new = $old + abs($valueAtomic);
        } elseif ($action == 'Decrement') {
            $new = $old - abs($valueAtomic);
        } else {
            throw new \InvalidArgumentException("Invalid action: $action.");
        }
        if ($new < 0) {
            throw new NexusException("New value($new) lte 0");
        }
        // for administrator, use english
        $modCommentText = Locale::trans('message.field_value_change_message_body', ['field' => Locale::trans("user.labels.{$sourceField}", [], 'en'), 'operator' => $operator->username, 'old' => $formatSize ? Format::size((float) $old) : $old, 'new' => $formatSize ? Format::size((float) $new) : $new, 'reason' => $reason], 'en');
        Logger::writeWithContext((string) "user: {$uid}, {$modCommentText}", (string) 'alert', (bool) false);
        $update = [
            $sourceField => $new,
            //            'modcomment' => DB::raw("if(modcomment = '', '$modCommentText', concat_ws('\n', '$modCommentText', modcomment))"),
        ];
        $locale = $targetUser->locale;
        $fieldLabel = Locale::trans("user.labels.{$sourceField}", [], $locale);
        $msg = Locale::trans('message.field_value_change_message_body', ['field' => $fieldLabel, 'operator' => $operator->username, 'old' => $formatSize ? Format::size((float) $old) : $old, 'new' => $formatSize ? Format::size((float) $new) : $new, 'reason' => $reason], $locale);
        $message = [
            'sender' => 0,
            'receiver' => $targetUser->id,
            'subject' => Locale::trans('message.field_value_change_message_subject', ['field' => $fieldLabel], $locale),
            'msg' => $msg,
            'added' => Carbon::now(),
        ];
        NexusDB::transaction(function () use ($uid, $sourceField, $old, $update, $message, $modCommentText) {
            $affectedRows = User::query()
                ->where('id', $uid)
                ->where($sourceField, $old)
                ->update($update);
            if ($affectedRows != 1) {
                throw new \RuntimeException("Change fail, affected rows != 1($affectedRows)");
            }
            Message::query()->insert($message);
            UserModifyLog::query()->insert([
                'user_id' => $uid,
                'content' => $modCommentText,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        });
        $this->clearCache($targetUser);

        return true;
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $uid
     */
    public function removeLeechWarn($operator, $uid): bool
    {
        $operator = $this->getUser($operator);
        $user = User::query()->findOrFail((int) $uid, User::$commonFields);
        $this->checkPermission($operator, $user);
        $this->clearCache($user);
        $user->leechwarn = 'no';
        $user->leechwarnuntil = null;

        return $user->save();
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $uid
     */
    public function removeTwoStepAuthentication($operator, $uid): bool
    {
        if (! $operator->canAccessAdmin()) {
            throw new \RuntimeException('No permission.');
        }
        $user = User::query()->findOrFail((int) $uid, User::$commonFields);
        $this->checkPermission($operator, $user);
        $this->clearCache($user);
        $user->two_step_secret = '';

        return $user->save();
    }

    /**
     * @param  mixed  $operator
     * @param  mixed  $user
     * @param  mixed  $status
     * @param  mixed  $disableReasonKey
     * @return mixed
     */
    public function updateDownloadPrivileges($operator, $user, $status, $disableReasonKey = null)
    {
        if (! in_array($status, ['yes', 'no'])) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }
        $targetUser = $this->getUser($user);
        if ($targetUser === null) {
            throw new \InvalidArgumentException('Target user not found');
        }
        $operator = $this->getUser($operator);
        $operatorUsername = 'System';
        if ($operator) {
            $operatorUsername = $operator->username;
            $this->checkPermission($operator, $targetUser);
        }
        $message = [
            'added' => now(),
            'receiver' => $targetUser->id,
        ];
        if ($status == 'no') {
            $update = ['downloadpos' => 'no'];
            $modComment = date('Y-m-d').' - Download disable by '.$operatorUsername;
            $msgTransPrefix = 'message.download_disable';
            if ($disableReasonKey !== null) {
                $msgTransPrefix .= "_$disableReasonKey";
            }
            $message['subject'] = Locale::trans("{$msgTransPrefix}.subject", [], $targetUser->locale);
            $message['msg'] = Locale::trans("{$msgTransPrefix}.body", ['operator' => $operatorUsername], $targetUser->locale);
        } else {
            $update = ['downloadpos' => 'yes'];
            $modComment = date('Y-m-d').' - Download enable by '.$operatorUsername;
            $message['subject'] = Locale::trans('message.download_enable.subject', [], $targetUser->locale);
            $message['msg'] = Locale::trans('message.download_enable.body', ['operator' => $operatorUsername], $targetUser->locale);
        }
        $result = NexusDB::transaction(function () use ($targetUser, $update, $modComment, $message) {
            Message::add($message);

            return $targetUser->updateWithModComment($update, $modComment);
        });
        $this->clearCache($targetUser);

        return $result;
    }

    /**
     * Mirror the legacy modtask uploadpos toggle.
     *
     * @param  mixed  $operator
     * @param  mixed  $user
     * @param  string  $status  'yes' or 'no'
     * @return mixed
     */
    public function updateUploadPrivileges($operator, $user, string $status)
    {
        if (! in_array($status, ['yes', 'no'])) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }
        $targetUser = $this->getUser($user);
        if ($targetUser === null) {
            throw new \InvalidArgumentException('Target user not found');
        }
        $operator = $this->getUser($operator);
        $operatorUsername = $operator ? $operator->username : 'System';
        if ($operator) {
            $this->checkPermission($operator, $targetUser);
        }
        $message = ['added' => now(), 'receiver' => $targetUser->id];
        if ($status == 'no') {
            $update = ['uploadpos' => 'no'];
            $modComment = date('Y-m-d').' - Upload disable by '.$operatorUsername;
            $message['subject'] = Locale::trans('message.upload_disable.subject', [], $targetUser->locale);
            $message['msg'] = Locale::trans('message.upload_disable.body', ['operator' => $operatorUsername], $targetUser->locale);
        } else {
            $update = ['uploadpos' => 'yes'];
            $modComment = date('Y-m-d').' - Upload enable by '.$operatorUsername;
            $message['subject'] = Locale::trans('message.upload_enable.subject', [], $targetUser->locale);
            $message['msg'] = Locale::trans('message.upload_enable.body', ['operator' => $operatorUsername], $targetUser->locale);
        }
        $result = NexusDB::transaction(function () use ($targetUser, $update, $modComment, $message) {
            Message::add($message);

            return $targetUser->updateWithModComment($update, $modComment);
        });
        $this->clearCache($targetUser);

        return $result;
    }

    /**
     * Mirror the legacy modtask forumpost toggle.
     *
     * @param  mixed  $operator
     * @param  mixed  $user
     * @param  string  $status  'yes' or 'no'
     * @return mixed
     */
    public function updateForumPost($operator, $user, string $status)
    {
        if (! in_array($status, ['yes', 'no'])) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }
        $targetUser = $this->getUser($user);
        if ($targetUser === null) {
            throw new \InvalidArgumentException('Target user not found');
        }
        $operator = $this->getUser($operator);
        $operatorUsername = $operator ? $operator->username : 'System';
        if ($operator) {
            $this->checkPermission($operator, $targetUser);
        }
        $message = ['added' => now(), 'receiver' => $targetUser->id];
        if ($status == 'no') {
            $update = ['forumpost' => 'no'];
            $modComment = date('Y-m-d').' - Forum posting disabled by '.$operatorUsername;
            $message['subject'] = Locale::trans('message.forumpost_disable.subject', [], $targetUser->locale);
            $message['msg'] = Locale::trans('message.forumpost_disable.body', ['operator' => $operatorUsername], $targetUser->locale);
        } else {
            $update = ['forumpost' => 'yes'];
            $modComment = date('Y-m-d').' - Forum posting enabled by '.$operatorUsername;
            $message['subject'] = Locale::trans('message.forumpost_enable.subject', [], $targetUser->locale);
            $message['msg'] = Locale::trans('message.forumpost_enable.body', ['operator' => $operatorUsername], $targetUser->locale);
        }
        $result = NexusDB::transaction(function () use ($targetUser, $update, $modComment, $message) {
            Message::add($message);

            return $targetUser->updateWithModComment($update, $modComment);
        });
        $this->clearCache($targetUser);

        return $result;
    }

    /**
     * Warn a user for a given number of weeks (mirrors legacy modtask warnlength).
     *
     * @param  mixed  $operator
     * @param  mixed  $user
     * @param  int  $weeks  0 = remove warning, 255 = indefinite
     * @param  string  $reason  PM reason text
     * @return mixed
     */
    public function warnUser($operator, $user, int $weeks, string $reason = '')
    {
        $targetUser = $this->getUser($user);
        if ($targetUser === null) {
            throw new \InvalidArgumentException('Target user not found');
        }
        $operator = $this->getUser($operator);
        $operatorId = $operator ? $operator->id : 0;
        $operatorUsername = $operator ? $operator->username : 'System';
        if ($operator) {
            $this->checkPermission($operator, $targetUser);
        }
        $locale = $targetUser->locale;
        $update = [];
        $message = ['added' => now(), 'receiver' => $targetUser->id, 'sender' => 0];

        if ($weeks === 0) {
            $update['warned'] = 'no';
            $update['warneduntil'] = null;
            $message['subject'] = Locale::trans('user.msg_warn_removed', [], $locale);
            $message['msg'] = Locale::trans('user.msg_your_warning_removed_by', [], $locale).$operatorUsername.'.';
        } else {
            $update['warned'] = 'yes';
            $update['lastwarned'] = now()->toDateTimeString();
            $update['warnedby'] = $operatorId;
            $update['timeswarned'] = new Expression('timeswarned + 1');
            if ($weeks == 255) {
                $update['warneduntil'] = null;
                $msg = Locale::trans('user.msg_you_are_warned_by', [], $locale).$operatorUsername.'.'.($reason ? Locale::trans('user.msg_reason', [], $locale).$reason : '');
            } else {
                $warneduntil = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s')) + $weeks * 604800);
                $update['warneduntil'] = $warneduntil;
                $dur = $weeks.Locale::trans('user.msg_week', [], $locale).($weeks > 1 ? Locale::trans('user.msg_s', [], $locale) : '');
                $msg = Locale::trans('user.msg_you_are_warned_for', [], $locale).$dur.Locale::trans('user.msg_by', [], $locale).$operatorUsername.'.'.($reason ? Locale::trans('user.msg_reason', [], $locale).$reason : '');
            }
            $message['subject'] = Locale::trans('user.msg_you_are_warned', [], $locale);
            $message['msg'] = $msg;
        }

        $result = NexusDB::transaction(function () use ($targetUser, $update, $message) {
            Message::add($message);
            $modComment = date('Y-m-d').' - Warning updated';

            return $targetUser->updateWithModComment($update, $modComment);
        });
        $this->clearCache($targetUser);

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
            NexusDB::transaction(function () use ($user, $meta, $params) {
                $this->changeUsername(
                    $user, UsernameChangeLog::CHANGE_TYPE_USER, $user, $params['username'],
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
        NexusDB::transaction(function () use ($targetUser, $changeLog) {
            $targetUser->usernameChangeLogs()->create($changeLog);
            $targetUser->username = $changeLog['username_new'];
            $targetUser->save();
        });
        $this->clearCache($targetUser);

        return true;
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
        Permission::assertCan(PermissionEnum::USER_CHANGE_CLASS);
        $newClass = (int) $newClass;
        $operator = $this->getUser($operator);
        $targetUser = $this->getUser($targetUser);
        if ($operator === null || $targetUser === null) {
            throw new \InvalidArgumentException('Operator or target user not found');
        }
        if ($operator->class <= $targetUser->class || $operator->class <= $newClass) {
            throw new InsufficientPermissionException;
        }
        if ($targetUser->class == $newClass && $newClass != User::CLASS_VIP) {
            return true;
        }
        $locale = $targetUser->locale;
        $subject = Locale::trans('user.edit_notifications.change_class.subject', [], $locale);
        $body = Locale::trans('user.edit_notifications.change_class.body', ['action' => Locale::trans('user.edit_notifications.change_class.'.($newClass > $targetUser->class ? 'promote' : 'demote'), [], null), 'new_class' => User::getClassText($newClass), 'operator' => $operator->username, 'reason' => $reason], $locale);
        $message = [
            'sender' => 0,
            'receiver' => $targetUser->id,
            'subject' => $subject,
            'msg' => $body,
            'added' => Carbon::now(),
        ];
        $userUpdates = [
            'class' => $newClass,
        ];
        if ($newClass == User::CLASS_VIP) {
            if (! empty($extra['vip_added']) && in_array($extra['vip_added'], ['yes', 'no'])) {
                $userUpdates['vip_added'] = $extra['vip_added'];
            } else {
                $userUpdates['vip_added'] = 'no';
            }
            if (! empty($extra['vip_until'])) {
                $until = Carbon::parse($extra['vip_until']);
                $userUpdates['vip_until'] = $until;
            } else {
                $userUpdates['vip_until'] = null;
            }
        } else {
            $userUpdates['vip_added'] = 'no';
            $userUpdates['vip_until'] = null;
        }
        Logger::writeWithContext((string) ('userUpdates: '.json_encode($userUpdates)), (string) 'info', (bool) false);
        NexusDB::transaction(function () use ($targetUser, $userUpdates, $message) {
            $modComment = date('Y-m-d').' - '.$message['msg'];
            if ($targetUser->class != $userUpdates['class']) {
                $targetUser->updateWithModComment($userUpdates, $modComment);
                Message::add($message);
            } else {
                $targetUser->update($userUpdates);
            }
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

    /** @param  mixed  $id */
    public function confirmUser($id): bool
    {
        $ids = Arr::wrap($id);
        $users = User::query()
            ->whereIn('id', $ids)
            ->where('status', User::STATUS_PENDING)
            ->get();

        if ($users->isEmpty()) {
            return true;
        }

        $update = [
            'status' => User::STATUS_CONFIRMED,
            'editsecret' => '',
        ];
        User::query()
            ->whereIn('id', $users->pluck('id'))
            ->update($update);

        foreach ($users as $user) {
            $user->status = User::STATUS_CONFIRMED;
            $user->editsecret = '';
            Events::fire(ModelEventEnum::USER_UPDATED, $user, null);
        }

        return true;
    }

    /**
     * Remove warnings from the given user IDs.
     *
     * Mirrors the legacy nowarn action: sets warned='no', warneduntil=NULL,
     * and prepends a modcomment noting who removed the warning.
     *
     * @param  array<int>  $userIds
     */
    public function removeWarnings(User $operator, array $userIds): void
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if (empty($userIds)) {
            return;
        }

        $modcomment = date('Y-m-d').' - Warning Removed By '.$operator->username;

        foreach ($userIds as $uid) {
            $user = User::query()->find($uid, ['id', 'warned', 'modcomment']);
            if ($user === null || $user->warned !== 'yes') {
                continue;
            }
            $newModcomment = $user->modcomment === '' || $user->modcomment === null
                ? $modcomment
                : $modcomment."\n".$user->modcomment;

            $user->warned = 'no';
            $user->warneduntil = null;
            $user->modcomment = $newModcomment;
            $user->save();

            Events::fire(ModelEventEnum::USER_UPDATED, $user, null);
        }
    }

    /**
     * @param  Collection<int, mixed>|int  $id
     * @param  mixed  $reasonKey
     * @return mixed
     */
    public function destroy(Collection|int $id, $reasonKey = 'user.destroy_by_admin')
    {
        if (! Environment::isConsole()) {
            Permission::assertCan(PermissionEnum::USER_DELETE);
        }
        if (is_int($id)) {
            $uidArr = Arr::wrap($id);
        } else {
            $uidArr = $id->pluck('id')->toArray();
        }
        $uidStr = implode(',', $uidArr);
        $users = User::query()->with('language')->whereIn('id', $uidArr)->get();
        if ($users->isEmpty()) {
            return true;
        }
        $tables = [
            'users' => 'id',
            'hit_and_runs' => 'uid',
            'exam_users' => 'uid',
            'exam_progress' => 'uid',
            'user_metas' => 'uid',
            'user_medals' => 'uid',
            'attendance' => 'uid',
            'attendance_logs' => 'uid',
            'login_logs' => 'uid',
            'seed_box_records' => 'uid',
            'user_modify_logs' => 'user_id',
            'messages' => 'receiver',
        ];
        foreach ($tables as $table => $key) {
            DB::table($table)->whereIn($key, $uidArr)->delete();
        }
        Logger::writeWithContext((string) ('[DESTROY_USER]: '.json_encode($uidArr)), (string) 'error', (bool) false);
        $userBanLogs = [];
        foreach ($users as $user) {
            $userBanLogs[] = [
                'uid' => $user->id,
                'username' => $user->username,
                'reason' => Locale::trans($reasonKey, [], $user->locale),
            ];
        }
        UserBanLog::query()->insert($userBanLogs);
        // delete by user, make sure torrent is deleted
        DB::table('snatched')
            ->whereIn('userid', $uidArr)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('torrents')->whereColumn('torrents.id', '=', 'snatched.torrentid');
            })
            ->delete();
        if (is_int($id)) {
            Hooks::doAction('user_delete', $id);
            Events::fire(ModelEventEnum::USER_DELETED, $users->first(), null);
        }

        return true;
    }

    /**
     * @return mixed
     */
    public function addTemporaryInvite(?User $operator, int $uid, string $action, int $count, ?int $days, ?string $reason = '')
    {
        Logger::writeWithContext((string) "uid: {$uid}, action: {$action}, count: {$count}, days: {$days}, reason: {$reason}", (string) 'info', (bool) false);
        $action = strtolower($action);
        if ($count <= 0 || ($action == 'increment' && $days <= 0)) {
            throw new \InvalidArgumentException('days or count lte 0');
        }
        $targetUser = User::query()->findOrFail((int) $uid, User::$commonFields);
        if ($operator) {
            $this->checkPermission($operator, $targetUser);
        }
        $toolRep = new ToolRepository;
        $locale = $targetUser->locale;

        $changeType = Locale::trans("nexus.{$action}", [], $locale);
        $subject = Locale::trans('message.temporary_invite_change.subject', ['change_type' => $changeType], $locale);
        $body = Locale::trans('message.temporary_invite_change.body', ['change_type' => $changeType, 'count' => $count, 'operator' => $operator->username ?? '', 'reason' => $reason], $locale);
        $message = [
            'sender' => 0,
            'receiver' => $targetUser->id,
            'subject' => $subject,
            'msg' => $body,
            'added' => Carbon::now(),
        ];
        $inviteData = [];
        if ($action == 'increment') {
            $hashArr = $toolRep->generateUniqueInviteHash([], $count, $count);
            foreach ($hashArr as $hash) {
                $inviteData[] = [
                    'inviter' => $uid,
                    'invitee' => '',
                    'hash' => $hash,
                    'valid' => 0,
                    'expired_at' => Carbon::now()->addDays((int) $days),
                    'created_at' => Carbon::now(),
                ];
            }
        }
        NexusDB::transaction(function () use ($uid, $message, $inviteData, $count, $operator) {
            if (! empty($inviteData)) {
                Invite::query()->insert($inviteData);
                Logger::writeWithContext((string) "[INSERT TEMPORARY INVITE] to {$uid}, count: {$count}", (string) 'info', (bool) false);
            } else {
                Invite::query()->where('inviter', $uid)
                    ->where('invitee', '')
                    ->orderBy('expired_at', 'asc')
                    ->limit($count)
                    ->delete();
                Logger::writeWithContext((string) "[DELETE TEMPORARY INVITE] of {$uid}, count: {$count}", (string) 'info', (bool) false);
            }
            if ($operator) {
                Message::add($message);
            }
        });

        return true;
    }

    /**
     * @return mixed
     */
    public function getInviteBtnText(int $uid)
    {
        if (! SiteConfig::current()->main->inviteSystem()) {
            throw new NexusException(Locale::trans('invite.send_deny_reasons.invite_system_closed', [], null));
        }
        if (! Permission::can(PermissionEnum::SEND_INVITE, User::findOrFail((int) $uid))) {
            $requireClass = SiteConfig::current()->authority->permission(PermissionEnum::SEND_INVITE->value);
            throw new NexusException(Locale::trans('invite.send_deny_reasons.no_permission', ['class' => User::getClassText((int) $requireClass)], null));
        }
        $userInfo = User::query()->findOrFail((int) $uid, User::$commonFields);
        $temporaryInviteCount = $userInfo->temporary_invites()->count();
        if ($userInfo->invites + $temporaryInviteCount < 1) {
            throw new NexusException(Locale::trans('invite.send_deny_reasons.invite_not_enough', [], null));
        }

        return Locale::trans('invite.send_allow_text', [], null);
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
            if ($row['seeder'] == 'yes') {
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
     * Find a user by id with the common field subset used by cache clearing.
     *
     * @return User|null
     */
    public function findForCacheClear(int|string $id)
    {
        return User::query()->find($id, User::$commonFields);
    }

    public static function findForDisplay(int|string $id): ?User
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

    public static function logModify(int|string $userId, string $comment): void
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
    public static function getByIds(array $ids, array $columns = ['*']): \Illuminate\Database\Eloquent\Collection
    {
        return User::query()->find($ids, $columns)->keyBy('id');
    }
}
