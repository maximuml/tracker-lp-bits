<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Http\Requests\TorrentIdRequest;
use App\Http\Requests\TorrentRequest;
use App\Http\Resources\TorrentOperationLogResource;
use App\Http\Resources\TorrentResource;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\TorrentDenyReason;
use App\Models\TorrentOperationLog;
use App\Models\User;
use App\Repositories\TorrentRepository;
use App\Repositories\UploadRepository;
use App\Support\Logger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class TorrentController extends Controller
{
    private TorrentRepository $repository;

    private UploadRepository $uploadRepository;

    public function __construct(TorrentRepository $repository, UploadRepository $uploadRepository)
    {
        $this->repository = $repository;
        $this->uploadRepository = $uploadRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function index(Request $request, ?string $section = null): array
    {
        Logger::writeWithContext((string) 'controller torrent index entry', (string) 'info', (bool) false);
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }
        $result = $this->repository->getList($request, $user, $section);
        Logger::writeWithContext((string) 'controller torrent index getList', (string) 'info', (bool) false);
        $resource = TorrentResource::collection($result);
        Logger::writeWithContext((string) 'controller torrent index prepare resource', (string) 'info', (bool) false);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(TorrentRequest $request): array
    {
        $uploadRep = $this->uploadRepository;
        $newTorrent = $uploadRep->upload($request);
        $resource = new JsonResource(['id' => $newTorrent->id]);

        return $this->success($resource);
    }

    /**
     * Display the specified resource.
     *
     * @return array<string, mixed>
     */
    public function show(int $id): array
    {
        Logger::writeWithContext((string) 'controller torrent show entry', (string) 'info', (bool) false);
        /**
         * @var User
         */
        $user = Auth::user();
        $torrent = $this->repository->getDetail($id, $user);
        Logger::writeWithContext((string) 'controller torrent show getDetail', (string) 'info', (bool) false);
        $resource = new TorrentResource($torrent);
        $additional = [];
        if ($this->hasExtraField('bonus_reward_values')) {
            $additional['bonus_reward_values'] = Setting::getBonusRewardOptions();
        }
        $this->appendExtraSettings($additional, []);
        $resource->additional($additional);
        Logger::writeWithContext((string) 'controller torrent show prepare resource', (string) 'info', (bool) false);

        return $this->success($resource);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  mixed  $id
     */
    public function update(Request $request, $id): Response
    {
        return new Response('', 204);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  mixed  $id
     */
    public function destroy($id): Response
    {
        return new Response('', 204);
    }

    /** @return  array<string, mixed> */
    public function searchBox(): array
    {
        $result = $this->repository->getSearchBox();

        return $this->success($result);
    }

    public function approvalPage(TorrentIdRequest $request): View
    {
        Permission::assertCan(PermissionEnum::TORRENT_APPROVAL);
        $torrentId = $request->torrent_id;
        $torrent = Torrent::query()->findOrFail($torrentId, Torrent::$commentFields);
        $denyReasons = TorrentDenyReason::query()->orderBy('priority', 'desc')->get();

        return view('torrent/approval', compact('torrent', 'denyReasons'));
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalLogs(TorrentIdRequest $request): array
    {
        Permission::assertCan(PermissionEnum::TORRENT_APPROVAL);
        $torrentId = $request->torrent_id;
        $actionTypes = [
            TorrentOperationLog::ACTION_TYPE_APPROVAL_NONE,
            TorrentOperationLog::ACTION_TYPE_APPROVAL_ALLOW,
            TorrentOperationLog::ACTION_TYPE_APPROVAL_DENY,
        ];
        $records = TorrentOperationLog::query()
            ->with(['user'])
            ->where('torrent_id', $torrentId)
            ->whereIn('action_type', $actionTypes)
            ->orderBy('id', 'desc')
            ->paginate($request->limit);

        $resource = TorrentOperationLogResource::collection($records);

        return $this->success($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function approval(Request $request): array
    {
        Permission::assertCan(PermissionEnum::TORRENT_APPROVAL);
        $request->validate([
            'torrent_id' => 'required',
            'approval_status' => 'required',
        ]);
        $params = $request->all();
        $this->repository->approval(Auth::user(), $params);

        return $this->success($params);
    }

    /**
     * @return array<string, mixed>
     */
    public function queryByPiecesHash(Request $request): array
    {
        $request->validate([
            'pieces_hash' => 'required|array',
        ]);
        $result = $this->repository->getPiecesHashCache($request->pieces_hash);

        return $this->success($result ?: (object) []);
    }
}
