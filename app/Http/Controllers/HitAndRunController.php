<?php

namespace App\Http\Controllers;

use App\Http\Resources\HitAndRunResource;
use App\Models\HitAndRun;
use App\Repositories\HitAndRunRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HitAndRunController extends Controller
{
    /** @var  mixed */
    private $repository;

    /**
     * @param  \App\Repositories\HitAndRunRepository  $repository
     * @return  mixed
     */
    public function __construct(HitAndRunRepository $repository)
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
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function index(Request $request)
    {
        $result = $this->repository->getList($request->all());
        $resource = HitAndRunResource::collection($result);
        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function store(Request $request)
    {
        $request->validate($this->getRules());
        $result = $this->repository->store($request->all());
        $resource = new HitAndRunResource($result);
        return $this->success($resource);
    }

    /**
     * Display the specified resource.
     * @param  mixed  $id
     * @return  array<string, mixed>
     */
    public function show($id)
    {
        $result = $this->repository->getDetail($id);
        $resource = new HitAndRunResource($result);
        return $this->success($resource);
    }

    /**
     * Update the specified resource in storage.
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $id
     * @return  array<string, mixed>
     */
    public function update(Request $request, $id)
    {
        $request->validate($this->getRules());
        $result = $this->repository->update($request->all(), $id);
        $resource = new HitAndRunResource($result);
        return $this->success($resource);
    }

    /**
     * Remove the specified resource from storage.
     * @param  mixed  $id
     * @return  array<string, mixed>
     */
    public function destroy($id)
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
     * @param  mixed  $id
     * @return  array<int|string, mixed>
     */
    public function pardon($id): array
    {
        $result = $this->repository->pardon($id, Auth::user());
        return $this->success($result);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return  array<int|string, mixed>
     */
    public function bulkPardon(Request $request): array
    {
        $result = $this->repository->bulkPardon($request->all(), Auth::user());
        return $this->success(['result' => $result],"Affected: " . intval($result));
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return  array<int|string, mixed>
     */
    public function bulkDelete(Request $request): array
    {
        $result = $this->repository->bulkDelete($request->all(), Auth::user());
        return $this->success(['result' => $result],"Affected: " . intval($result));
    }
}
