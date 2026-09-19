<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\UserClass as UserClassEnum;
use App\Enums\UserStatus;
use App\Events\UserCreated;
use App\Exceptions\InsufficientPermissionException;
use App\Http\Resources\UserResource;
use App\Models\LoginLog;
use App\Models\User;
use App\Models\UserModifyLog;
use App\Services\UserStatsService;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Email;
use App\Support\Environment;
use App\Support\LegacyRuntime;
use App\Support\Logger;
use App\Support\Network;
use App\Support\PasswordHasher;
use App\Support\Security\PasskeyGenerator;
use App\Support\Token;
use App\Support\UserDisplay;
use App\Support\Validators;
use App\Utils\ApiQueryBuilder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * User repository: listing, detail, CRUD, meta, and auth helpers.
 *
 * Moderation and administration logic has been extracted to:
 *
 * @see UserModerationRepository
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly UserStatsService $statsService = new UserStatsService,
        private readonly UserMetaRepository $metaRepository = new UserMetaRepository,
        private readonly PasskeyGenerator $passkeyGenerator = new PasskeyGenerator,
        private readonly LegacyRuntime $legacyRuntime = new LegacyRuntime,
    ) {
        //
    }

    /** @return list<string> */
    protected function allowedSortColumns(): array
    {
        return ['id', 'username', 'email', 'class', 'added', 'last_access'];
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
            $seedingData = $this->statsService->listUserSeedingLeechingData($idArr);
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
            if (! $this->legacyRuntime->isLegacy()) {
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
            'passkey' => $this->passkeyGenerator->generate(),
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
        event(new UserCreated($user));

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
        return $this->metaRepository->listMetas($uid, $metaKeys, $valid);
    }

    /**
     * @param  mixed  $uid
     * @param  array<int|string, mixed>  $params
     */
    public function consumeBenefit($uid, array $params): bool
    {
        return $this->metaRepository->consumeBenefit($uid, $params);
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
        return $this->metaRepository->addMeta($user, $metaData, $keyExistsUpdates, $notify);
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
     * @return Collection<int, User>
     */
    public function getByIds(array $ids, array $columns = ['*']): Collection
    {
        return User::query()->find($ids, $columns)->keyBy('id');
    }
}
