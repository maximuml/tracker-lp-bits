<?php

namespace App\Http\Controllers;

use App\Http\Resources\MedalResource;
use App\Repositories\MedalRepository;
use Illuminate\Http\Request;

class MedalController extends Controller
{
    /** @var  mixed */
    private $repository;

    /**
     * @param  \App\Repositories\MedalRepository  $repository
     * @return  mixed
     */
    public function __construct(MedalRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function index(Request $request)
    {
        $result = $this->repository->getList($request->all());
        $resource = MedalResource::collection($result);
        $resource->additional([
            'page_title' => \App\Support\Locale::trans('medal.admin.list.page_title', [], null),
        ]);
        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string',
            'price' => 'required|integer|min:1',
            'image_large' => 'required|url',
            'image_small' => 'required|url',
            'duration' => 'nullable|integer|min:-1',
        ];
        $request->validate($rules);
        $result = $this->repository->store($request->all());
        $resource = new MedalResource($result);
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
        $resource = new MedalResource($result);
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
        $rules = [
            'name' => 'required|string',
            'price' => 'required|integer|min:1',
            'image_large' => 'required|url',
            'image_small' => 'required|url',
            'duration' => 'nullable|integer|min:-1',
        ];
        $request->validate($rules);
        $result = $this->repository->update($request->all(), $id);
        $resource = new MedalResource($result);
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
        return $this->success($result, 'Delete medal success!');
    }


}
