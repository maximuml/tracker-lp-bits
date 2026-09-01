<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PeerSeeder;
use App\Enums\SnatchFinished;
use App\Http\Requests\UidRequest;
use App\Http\Requests\UserDisableRequest;
use App\Http\Requests\UserIncrementDecrementRequest;
use App\Http\Requests\UserResetPasswordRequest;
use App\Http\Requests\UserStoreRequest;
use App\Http\Resources\ExamResource;
use App\Http\Resources\InviteResource;
use App\Http\Resources\TorrentResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\ExamRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    private UserRepository $repository;

    private ExamRepository $examRepository;

    public function __construct(UserRepository $repository, ExamRepository $examRepository)
    {
        $this->repository = $repository;
        $this->examRepository = $examRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $result = $this->repository->getList($request->all());
        $resource = UserResource::collection($result);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(UserStoreRequest $request): array
    {
        $result = $this->repository->store($request->validated());
        $resource = new UserResource($result);

        return $this->success($resource);
    }

    /**
     * Display the specified resource.
     *
     * @param  mixed  $id
     * @return array<string, mixed>
     */
    public function show($id = null): array
    {
        $currentUser = Auth::user();
        if (! $currentUser instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }
        if ($id === null) {
            $id = $currentUser->id;
        }
        $result = $this->repository->getDetail((int) $id, $currentUser);
        $resource = new UserResource($result);

        return $this->success($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function resetPassword(UserResetPasswordRequest $request): array
    {
        $result = $this->repository->resetPassword((int) $request->uid, $request->password, $request->password_confirmation);

        return $this->success($result, 'Reset password success!');
    }

    /** @return  array<string, mixed> */
    public function classes(): array
    {
        $result = User::listClass();

        return $this->success($result);
    }

    /** @return  array<string, mixed> */
    public function base(): array
    {
        $id = (int) Auth::id();
        $result = $this->repository->getBase($id);
        $resource = new UserResource($result);

        return $this->success($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function matchExams(UidRequest $request): array
    {
        $examRepository = $this->examRepository;
        $result = $examRepository->listMatchExam((int) $request->uid);
        $resource = ExamResource::collection($result);

        return $this->success($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function disable(UserDisableRequest $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }
        $result = $this->repository->disableUser($user, (int) $request->uid, $request->reason);

        return $this->success($result, 'Disable user success!');
    }

    /**
     * @return array<string, mixed>
     */
    public function enable(UidRequest $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }
        $result = $this->repository->enableUser($user, (int) $request->uid);

        return $this->success($result, 'Enable user success!');
    }

    /**
     * @return array<string, mixed>
     */
    public function inviteInfo(UidRequest $request): array
    {
        $result = $this->repository->getInviteInfo((int) $request->uid);
        $resource = $result ? (new InviteResource($result)) : null;

        return $this->success($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function modComment(UidRequest $request): array
    {
        $result = $this->repository->getModComment((int) $request->uid);

        return $this->success($result);
    }

    /** @return  array<string, mixed> */
    public function me(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }

        $resource = $this->getUserProfile($user->id);

        return $this->success($resource);
    }

    private function getUserProfile(int $id): UserResource
    {
        $user = User::query()->withCount([
            'comments', 'posts', 'seeding_torrents', 'leeching_torrents',
            'torrents' => function ($query) {
                $query->whereHas('snatches');
            },
            'completed_torrents' => function ($query) use ($id) {
                $query->where('torrents.owner', '!=', $id);
            },
            'incomplete_torrents' => function ($query) use ($id) {
                $query->where('torrents.owner', '!=', $id);
            },
        ])->findOrFail($id);
        $resource = new UserResource($user);

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function publishTorrent(Request $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }

        $result = $user->torrents()->orderBy('id', 'desc')->paginate();

        $resource = TorrentResource::collection($result);

        return $this->success($resource);

    }

    /**
     * @return array<string, mixed>
     */
    public function seedingTorrent(Request $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }

        $result = $user->peers_torrents()->where('seeder', PeerSeeder::YES->value)->orderBy('torrent', 'desc')->paginate();

        $resource = TorrentResource::collection($result);

        return $this->success($resource);

    }

    /**
     * @return array<string, mixed>
     */
    public function leechingTorrent(Request $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }

        $result = $user->peers_torrents()->where('seeder', PeerSeeder::NO->value)->orderBy('torrent', 'desc')->paginate();

        $resource = TorrentResource::collection($result);

        return $this->success($resource);

    }

    /**
     * @return array<string, mixed>
     */
    public function finishedTorrent(Request $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }

        $result = $user->snatched_torrents()
            ->where('owner', '<>', $user->id)
            ->where('finished', SnatchFinished::YES->value)
            ->orderBy('torrentid', 'desc')
            ->paginate();

        $resource = TorrentResource::collection($result);

        return $this->success($resource);

    }

    /**
     * @return array<string, mixed>
     */
    public function notFinishedTorrent(Request $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }

        $result = $user->snatched_torrents()
            ->where('owner', '<>', $user->id)
            ->where('finished', SnatchFinished::NO->value)
            ->orderBy('torrentid', 'desc')
            ->paginate();

        $resource = TorrentResource::collection($result);

        return $this->success($resource);

    }

    /**
     * @return array<int|string, mixed>
     */
    public function incrementDecrement(UserIncrementDecrementRequest $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }
        $result = $this->repository->incrementDecrement($user, $request->uid, $request->action, $request->field, $request->value, $request->reason);

        return $this->success(['success' => $result]);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function removeTwoStepAuthentication(UidRequest $request): array
    {
        $user = Auth::user();
        $result = $this->repository->removeTwoStepAuthentication($user, $request->uid);

        return $this->success(['success' => $result]);
    }
}
