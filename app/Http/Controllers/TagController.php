<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GenericIndexRequest;
use App\Http\Requests\TagStoreRequest;
use App\Http\Requests\TagUpdateRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Repositories\TagRepository;

class TagController extends Controller
{
    private TagRepository $repository;

    /**
     * @return mixed
     */
    public function __construct(TagRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return array<string, mixed>
     */
    public function index(GenericIndexRequest $request): array
    {
        $result = $this->repository->getList($request->validated());
        $resource = TagResource::collection($result);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(TagStoreRequest $request): array
    {
        $data = array_filter($request->validated());
        $result = $this->repository->store($data);
        $resource = new TagResource($result);

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
        $result = Tag::query()->findOrFail($id);
        $resource = new TagResource($result);

        return $this->success($resource);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  mixed  $id
     * @return array<string, mixed>
     */
    public function update(TagUpdateRequest $request, $id): array
    {
        $data = $request->validated();
        if (isset($data['priority'])) {
            $data['priority'] = intval($data['priority']);
        }
        $result = $this->repository->update($data, $id);
        $resource = new TagResource($result);

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
