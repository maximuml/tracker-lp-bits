<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\HitAndRunBulkRequest;
use App\Http\Requests\HitAndRunIndexRequest;
use App\Http\Requests\HitAndRunRequest;
use App\Http\Resources\HitAndRunResource;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use Illuminate\Support\Facades\Auth;

class HitAndRunController extends Controller
{
    private HitAndRunRepository $repository;

    public function __construct(HitAndRunRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return array<string, mixed>
     */
    public function index(HitAndRunIndexRequest $request): array
    {
        $result = $this->repository->getList($request->validated());
        $resource = HitAndRunResource::collection($result);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(HitAndRunRequest $request): array
    {
        $data = $request->validated();
        $result = $this->repository->store($data);
        $resource = new HitAndRunResource($result);

        return $this->success($resource);
    }

    /**
     * Display the specified resource.
     *
     * @return array<string, mixed>
     */
    public function show(int $id): array
    {
        $result = $this->repository->getDetail($id);
        $resource = new HitAndRunResource($result);

        return $this->success($resource);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return array<string, mixed>
     */
    public function update(HitAndRunRequest $request, int $id): array
    {
        $data = $request->validated();
        $result = $this->repository->update($data, $id);
        $resource = new HitAndRunResource($result);

        return $this->success($resource);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return array<string, mixed>
     */
    public function destroy(int $id): array
    {
        $result = $this->repository->delete($id);

        return $this->success($result);
    }

    /** @return  array<int|string, mixed> */
    public function listStatus(): array
    {
        $result = $this->repository->listStatus();

        return $this->success($result);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function pardon(int $id): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return $this->success(['result' => false], 'Unauthenticated');
        }
        $result = $this->repository->pardon($id, $user);

        return $this->success($result);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function bulkPardon(HitAndRunBulkRequest $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return $this->success(['result' => false], 'Unauthenticated');
        }
        $result = $this->repository->bulkPardon($request->validated(), $user);

        return $this->success(['result' => $result], 'Affected: '.intval($result));
    }

    /**
     * @return array<int|string, mixed>
     */
    public function bulkDelete(HitAndRunBulkRequest $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return $this->success(['result' => false], 'Unauthenticated');
        }
        $result = $this->repository->bulkDelete($request->validated(), $user);

        return $this->success(['result' => $result], 'Affected: '.intval($result));
    }
}
