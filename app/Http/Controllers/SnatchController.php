<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SnatchRequest;
use App\Http\Resources\SnatchResource;
use App\Services\TorrentStatsService;

class SnatchController extends Controller
{
    private TorrentStatsService $statsService;

    /**
     * @return mixed
     */
    public function __construct(TorrentStatsService $statsService)
    {
        $this->statsService = $statsService;
    }

    /**
     * @return array<string, mixed>
     */
    public function index(SnatchRequest $request): array
    {
        $snatches = $this->statsService->listSnatches($request->torrent_id);
        $resource = SnatchResource::collection($snatches);

        return $this->success($resource);
    }
}
