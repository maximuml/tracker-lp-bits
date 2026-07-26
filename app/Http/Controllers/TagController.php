<?php

namespace App\Http\Controllers;

use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Repositories\TagRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    /** @var  mixed */
    private $repository;

    /**
     * @param  \App\Repositories\TagRepository  $repository
     * @return  mixed
     */
    public function __construct(TagRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param  mixed  $id
     * @return  array<int|string, mixed>
     */
    private function getRules($id = null): array
    {
        return [
            'name' => ['required', 'string', Rule::unique('tags', 'name')->ignore($id)],
            'color' => 'required|string',
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
        $resource = TagResource::collection($result);

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
        $data = array_filter($request->all());
        $result = $this->repository->store($data);
        $resource = new TagResource($result);
        return $this->success($resource);
    }

    /**
     * Display the specified resource.
     * @param  mixed  $id
     * @return  array<string, mixed>
     */
    public function show($id)
    {
        $result = Tag::query()->findOrFail($id);
        $resource = new TagResource($result);
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
        $request->validate($this->getRules($id));
        $data = $request->all();
        if (isset($data['priority'])) {
            $data['priority'] = intval($data['priority']);
        }
        $result = $this->repository->update($data, $id);
        $resource = new TagResource($result);
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
}
