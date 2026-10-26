<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserClass as UserClassEnum;
use App\Http\Requests\SettingIndexRequest;
use App\Http\Requests\SettingStoreRequest;
use App\Models\User;
use App\Repositories\SettingRepository;
use Illuminate\Support\Facades\Auth;

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
        $this->assertSysop();
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
        $this->assertSysop();
        $data = $request->validated();
        $result = $this->repository->store($data);

        return $this->success($result, 'Save setting success!');
    }

    /**
     * The settings API mirrors the web settings panel, which is restricted
     * to UC_SYSOP — a Sanctum ability alone is not sufficient because API
     * clients are issued wildcard tokens.
     */
    private function assertSysop(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && (int) $user->class >= UserClassEnum::SYSOP->value, 403);
    }
}
