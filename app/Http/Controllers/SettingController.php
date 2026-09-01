<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SettingStoreRequest;
use App\Repositories\SettingRepository;
use Illuminate\Http\Request;

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
    public function index(Request $request): array
    {
        $result = $this->repository->getList($request->all());

        return $this->success($result);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(SettingStoreRequest $request): array
    {
        $data = $request->all();
        $result = $this->repository->store($data);

        return $this->success($result, 'Save setting success!');
    }
}
