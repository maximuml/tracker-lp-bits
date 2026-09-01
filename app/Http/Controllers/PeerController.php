<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PeerRequest;
use App\Http\Resources\PeerResource;
use App\Repositories\TorrentRepository;

class PeerController extends Controller
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
     * Display a listing of the resource.
     *
     * @return array<string, mixed>
     */
    public function index(PeerRequest $request): array
    {
        $response = [
            'seeder_list' => [],
            'leecher_list' => [],
        ];
        $result = $this->repository->listPeers($request->torrent_id);
        if ($result['seeder_list']->isNotEmpty()) {
            $response['seeder_list'] = PeerResource::collection($result['seeder_list']);
        }
        if ($result['leecher_list']->isNotEmpty()) {
            $response['leecher_list'] = PeerResource::collection($result['leecher_list']);
        }

        return $this->success($response);

    }
}
