<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AgentDenyRequest;
use App\Http\Resources\AgentDenyResource;
use App\Models\AgentDeny;
use App\Repositories\AgentDenyRepository;
use Illuminate\Http\Request;

class AgentDenyController extends Controller
{
    private AgentDenyRepository $repository;

    /**
     * @return mixed
     */
    public function __construct(AgentDenyRepository $repository)
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
        $resource = AgentDenyResource::collection($result);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(AgentDenyRequest $request): array
    {
        $result = $this->repository->store($request->validated());
        $resource = new AgentDenyResource($result);

        return $this->success($resource);
    }

    /**
     * Display the specified resource.
     *
     * @param  mixed  $id
     * @return array<string, mixed>
     */
    public function show($id): array
    {
        $result = AgentDeny::query()->findOrFail($id);
        $resource = new AgentDenyResource($result);

        return $this->success($resource);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  mixed  $id
     * @return array<string, mixed>
     */
    public function update(AgentDenyRequest $request, $id): array
    {
        $result = $this->repository->update($request->validated(), $id);
        $resource = new AgentDenyResource($result);

        return $this->success($resource);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  mixed  $id
     * @return array<string, mixed>
     */
    public function destroy($id): array
    {
        $result = $this->repository->delete($id);

        return $this->success($result);
    }
}
