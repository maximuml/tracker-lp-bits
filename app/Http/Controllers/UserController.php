<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExamResource;
use App\Http\Resources\InviteResource;
use App\Http\Resources\TorrentResource;
use App\Http\Resources\UserResource;
use App\Models\Peer;
use App\Models\Snatch;
use App\Models\User;
use App\Repositories\ExamRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /** @var mixed */
    private $repository;

    /**
     * @return mixed
     */
    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
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
    public function store(Request $request): array
    {
        $rules = [
            'username' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|max:40',
            'password_confirmation' => 'required|string|same:password',
        ];
        $request->validate($rules);
        $result = $this->repository->store($request->all());
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
     * Update the specified resource in storage.
     *
     * @param  mixed  $id
     */
    public function update(Request $request, $id): Response
    {
        //

        return new Response('');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  mixed  $id
     */
    public function destroy($id): Response
    {
        //

        return new Response('');
    }

    /**
     * @return array<string, mixed>
     */
    public function resetPassword(Request $request): array
    {
        $rules = [
            'uid' => 'required',
            'password' => 'required|string|min:6|max:40',
            'password_confirmation' => 'required|same:password',
        ];
        $request->validate($rules);
        $result = $this->repository->resetPassword((int) $request->uid, $request->password, $request->password_confirmation);

        return $this->success($result, 'Reset password success!');
    }

    /** @return  array<string, mixed> */
    public function classes(): array
    {
        $result = $this->repository->listClass();

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
    public function matchExams(Request $request): array
    {
        $request->validate([
            'uid' => 'required',
        ]);
        $examRepository = new ExamRepository;
        $result = $examRepository->listMatchExam((int) $request->uid);
        $resource = ExamResource::collection($result);

        return $this->success($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function disable(Request $request): array
    {
        $request->validate([
            'uid' => 'required',
            'reason' => 'required',
        ]);
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
    public function enable(Request $request): array
    {
        $request->validate([
            'uid' => 'required',
        ]);
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
    public function inviteInfo(Request $request): array
    {
        $request->validate([
            'uid' => 'required',
        ]);
        $result = $this->repository->getInviteInfo((int) $request->uid);
        $resource = $result ? (new InviteResource($result)) : null;

        return $this->success($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function modComment(Request $request): array
    {
        $request->validate([
            'uid' => 'required',
        ]);
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

        $result = $user->peers_torrents()->where('seeder', Peer::SEEDER_YES)->orderBy('torrent', 'desc')->paginate();

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

        $result = $user->peers_torrents()->where('seeder', Peer::SEEDER_NO)->orderBy('torrent', 'desc')->paginate();

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
            ->where('finished', Snatch::FINISHED_YES)
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
            ->where('finished', Snatch::FINISHED_NO)
            ->orderBy('torrentid', 'desc')
            ->paginate();

        $resource = TorrentResource::collection($result);

        return $this->success($resource);

    }

    /**
     * @return array<int|string, mixed>
     */
    public function incrementDecrement(Request $request): array
    {
        $user = Auth::user();
        $request->validate([
            'uid' => 'required',
            'action' => 'required',
            'field' => 'required',
            'value' => 'required|numeric',
        ]);
        $result = $this->repository->incrementDecrement($user, $request->uid, $request->action, $request->field, $request->value, $request->reason);

        return $this->success(['success' => $result]);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function removeTwoStepAuthentication(Request $request): array
    {
        $user = Auth::user();
        $request->validate([
            'uid' => 'required',
        ]);
        $result = $this->repository->removeTwoStepAuthentication($user, $request->uid);

        return $this->success(['success' => $result]);
    }
}
