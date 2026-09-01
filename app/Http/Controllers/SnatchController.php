<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SnatchRequest;
use App\Http\Resources\SnatchResource;
use App\Repositories\TorrentRepository;

class SnatchController extends Controller
{
    private TorrentRepository $repository;

    /**
     * @return mixed
     */
    public function __construct(TorrentRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array<string, mixed>
     */
    public function index(SnatchRequest $request): array
    {
        $snatches = $this->repository->listSnatches($request->torrent_id);
        $resource = SnatchResource::collection($snatches);

        return $this->success($resource);
    }
}
