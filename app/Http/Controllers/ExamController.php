<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ExamResource;
use App\Models\Exam;
use App\Repositories\ExamRepository;
use App\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExamController extends Controller
{
    private ExamRepository $repository;

    /**
     * @return mixed
     */
    public function __construct(ExamRepository $repository)
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
        $resource = ExamResource::collection($result);
        $resource->additional([
            'page_title' => Locale::trans('exam.admin.list.page_title', [], null),
        ]);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(Request $request): array
    {
        $rules = [
            'name' => 'required|string',
            'indexes' => 'required|array|min:1',
            'indexes.*.index' => ['required', Rule::in(array_keys(Exam::$indexes))],
            'indexes.*.require_value' => 'nullable|numeric',
            'status' => 'required|in:0,1',
            'duration' => 'nullable|numeric',
        ];
        $request->validate($rules);
        $result = $this->repository->store($request->all());
        $resource = new ExamResource($result);

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
        $resource = new ExamResource($result);

        return $this->success($resource);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return array<string, mixed>
     */
    public function update(Request $request, int $id): array
    {
        $rules = [
            'name' => 'required|string',
            'indexes' => 'required|array|min:1',
            'indexes.*.index' => ['required', Rule::in(array_keys(Exam::$indexes))],
            'indexes.*.require_value' => 'nullable|numeric',
            'status' => 'required|in:0,1',
            'duration' => 'nullable|numeric',
        ];
        $request->validate($rules);
        $result = $this->repository->update($request->all(), $id);
        $resource = new ExamResource($result);

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

        return $this->success($result, 'Delete exam success!');
    }

    /** @return  array<string, mixed> */
    public function indexes(): array
    {
        $result = $this->repository->listIndexes();

        return $this->success($result);
    }

    /** @return  array<string, mixed> */
    public function all(): array
    {
        $result = Exam::query()->orderBy('id', 'desc')->get();
        $resource = ExamResource::collection($result);

        return $this->success($resource);
    }
}
