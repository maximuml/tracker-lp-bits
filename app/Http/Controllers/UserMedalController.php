<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UserMedalStoreRequest;
use App\Http\Requests\UserMedalUpdateRequest;
use App\Http\Resources\MedalResource;
use App\Models\UserMedal;
use App\Repositories\MedalRepository;
use App\Support\Locale;
use Illuminate\Http\Request;

class UserMedalController extends Controller
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
    public function index(Request $request): array
    {
        $result = $this->repository->getList($request->all());
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
    public function store(UserMedalStoreRequest $request): array
    {
        $result = $this->repository->grantToUser($request->uid, $request->medal_id, $request->duration);

        return $this->success($result);
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
    public function update(UserMedalUpdateRequest $request, $id): array
    {
        $result = $this->repository->update($request->all(), $id);
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
        $userMedal = UserMedal::query()->findOrFail((int) $id);
        $result = $userMedal->delete();

        return $this->success($result, 'Remove user medal success!');
    }
}
