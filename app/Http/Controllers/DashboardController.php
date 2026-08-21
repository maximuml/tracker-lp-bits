<?php

namespace App\Http\Controllers;

use App\Http\Resources\TorrentResource;
use App\Http\Resources\UserResource;
use App\Repositories\DashboardRepository;
use App\Support\Locale;

class DashboardController extends Controller
{
    /** @var mixed */
    private $repository;

    /**
     * @return mixed
     */
    public function __construct(DashboardRepository $repository)
    {
        $this->repository = $repository;
    }

    /** @return  array<string, mixed> */
    public function systemInfo()
    {
        $result = $this->repository->getSystemInfo();

        return $this->success($result);
    }

    /** @return  array<string, mixed> */
    public function statData()
    {
        $result = $this->repository->getStatData();

        return $this->success($result);
    }

    /** @return  array<string, mixed> */
    public function latestUser()
    {
        $result = $this->repository->latestUser();
        $resource = UserResource::collection($result);
        $resource->additional([
            'page_title' => Locale::trans('dashboard.latest_user.page_title', [], null),
        ]);

        return $this->success($resource);
    }

    /** @return  array<string, mixed> */
    public function latestTorrent()
    {
        $result = $this->repository->latestTorrent();
        $resource = TorrentResource::collection($result);
        $resource->additional([
            'page_title' => Locale::trans('dashboard.latest_torrent.page_title', [], null),
        ]);

        return $this->success($resource);
    }
}
