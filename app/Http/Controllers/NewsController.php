<?php

namespace App\Http\Controllers;

use App\Http\Resources\NewsResource;
use App\Models\News;
use App\Models\User;
use App\Repositories\NewsRepository;
use App\Support\Site;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class NewsController extends Controller
{
    /** @var mixed */
    private $repository;

    /**
     * @return mixed
     */
    public function __construct(NewsRepository $repository)
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
    public function index(Request $request)
    {
        $result = $this->repository->getList($request->all());
        $resource = NewsResource::collection($result);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(Request $request)
    {
        $request->validate($this->getRules());
        $result = $this->repository->store($request->all());
        $resource = new NewsResource($result);

        return $this->success($resource);
    }

    /**
     * Display the specified resource.
     *
     * @param  mixed  $id
     * @return array<string, mixed>
     */
    public function show($id)
    {
        $result = News::query()->findOrFail($id);
        $resource = new NewsResource($result);

        return $this->success($resource);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  mixed  $id
     * @return array<string, mixed>
     */
    public function update(Request $request, $id)
    {
        $request->validate($this->getRules());
        $result = $this->repository->update($request->all(), $id);
        $resource = new NewsResource($result);

        return $this->success($resource);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  mixed  $id
     * @return array<string, mixed>
     */
    public function destroy($id)
    {
        $result = $this->repository->delete($id);

        return $this->success($result);
    }

    /** @return  array<string, mixed> */
    public function latest()
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return $this->success(new JsonResource(null));
        }
        $result = News::query()->orderBy('id', 'desc')->first();
        if ($result) {
            $resource = new NewsResource($result);
        } else {
            $resource = new JsonResource(null);
        }
        $resource->additional([
            'site_info' => Site::info(),
        ]);

        /**
         * Visiting the home page is the same as viewing the latest news
         *
         * @see functions.php line 2590
         */
        $user->update(['last_home' => Carbon::now()]);

        return $this->success($resource);
    }
}
