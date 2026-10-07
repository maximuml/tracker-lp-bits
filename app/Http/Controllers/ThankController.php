<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Http\Requests\ThankRequest;
use App\Http\Resources\ThankResource;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\TorrentDetailRepository;
use App\Services\ThankService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ThankController extends Controller
{
    public function __construct(private readonly TorrentRepositoryInterface $torrentRepository, private readonly TorrentDetailRepository $torrentDetailRepository,
        private readonly ThankService $thankService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $torrentId = $request->torrent_id;
        $thanks = $this->torrentDetailRepository->paginateThanks($torrentId);
        $resource = ThankResource::collection($thanks);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(ThankRequest $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }
        $torrentId = (int) $request->torrent_id;
        $torrent = $this->torrentRepository->findOrFailById($torrentId, Torrent::$commentFields);
        $torrent->checkIsNormal();

        $result = $this->thankService->thankTorrent($user, $torrent);
        $resource = new ThankResource($result);

        return $this->success($resource, __('details.text_thanks_added'));
    }
}
