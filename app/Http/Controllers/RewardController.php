<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RewardIndexRequest;
use App\Http\Requests\RewardRequest;
use App\Http\Resources\RewardResource;
use App\Models\User;
use App\Repositories\RewardRepository;
use App\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class RewardController extends Controller
{
    private RewardRepository $repository;

    /**
     * @return mixed
     */
    public function __construct(RewardRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array<string, mixed>
     */
    public function index(RewardIndexRequest $request): array
    {
        $result = $this->repository->getList($request->all());
        $resource = RewardResource::collection($result);
        $resource->additional([
            'page_title' => Locale::trans('reward.index.page_title', [], null),
        ]);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(RewardRequest $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }
        $result = $this->repository->store($request->torrent_id, $request->value, $user);
        $resource = new RewardResource($result);

        return $this->success($resource, '赠魔成功！');
    }

    /**
     * Display the specified resource.
     *
     * @param  mixed  $id
     */
    public function show($id): Response
    {
        //

        return new Response('');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  mixed  $id
     */
    public function update(Request $request, $id): Response
    {
        //

        return new Response('');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  mixed  $id
     */
    public function destroy($id): Response
    {
        //

        return new Response('');
    }
}
