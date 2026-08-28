<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

    /** @return  array<int|string, mixed> */
    private function getRules(): array
    {
        return [
            'family_id' => 'required|numeric',
            'name' => 'required|string',
            'peer_id' => 'required|string',
            'agent' => 'required|string',
            'comment' => 'required|string',

        ];
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
    public function store(Request $request): array
    {
        $request->validate($this->getRules());
        $result = $this->repository->store($request->all());
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
    public function update(Request $request, $id): array
    {
        $request->validate($this->getRules());
        $result = $this->repository->update($request->all(), $id);
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
