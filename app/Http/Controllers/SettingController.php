<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SettingIndexRequest;
use App\Http\Requests\SettingStoreRequest;
use App\Repositories\SettingRepository;

class SettingController extends Controller
{
    private SettingRepository $repository;

    /**
     * @return mixed
     */
    public function __construct(SettingRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return array<string, mixed>
     */
    public function index(SettingIndexRequest $request): array
    {
        $result = $this->repository->getList($request->validated());

        return $this->success($result);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(SettingStoreRequest $request): array
    {
        $data = $request->validated();
        $result = $this->repository->store($data);

        return $this->success($result, 'Save setting success!');
    }
}
