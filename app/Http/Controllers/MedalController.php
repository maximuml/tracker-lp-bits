<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GenericIndexRequest;
use App\Http\Requests\MedalStoreRequest;
use App\Http\Requests\MedalUpdateRequest;
use App\Http\Resources\MedalResource;
use App\Repositories\MedalRepository;
use App\Support\Locale;

class MedalController extends Controller
{
    private MedalRepository $repository;

    /**
     * @return mixed
     */
    public function __construct(MedalRepository $repository)
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
        $resource = MedalResource::collection($result);
        $resource->additional([
            'page_title' => Locale::trans('medal.admin.list.page_title', [], null),
        ]);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(MedalStoreRequest $request): array
    {
        $result = $this->repository->store($request->validated());
        $resource = new MedalResource($result);

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
        $result = $this->repository->getDetail($id);
        $resource = new MedalResource($result);

        return $this->success($resource);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  mixed  $id
     * @return array<string, mixed>
     */
    public function update(MedalUpdateRequest $request, $id): array
    {
        $result = $this->repository->update($request->validated(), $id);
        $resource = new MedalResource($result);

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

        return $this->success($result, 'Delete medal success!');
    }
}
