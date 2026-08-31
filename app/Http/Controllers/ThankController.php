<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ThankRequest;
use App\Http\Resources\ThankResource;
use App\Models\Thank;
use App\Models\Torrent;
use App\Models\User;
use App\Services\ThankService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ThankController extends Controller
{
    public function __construct(
        private readonly ThankService $thankService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $torrentId = $request->torrent_id;
        $thanks = Thank::query()
            ->where('torrentid', $torrentId)
            ->whereHas('user')
            ->with(['user'])
            ->paginate();
        $resource = ThankResource::collection($thanks);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(ThankRequest $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }
        $torrentId = (int) $request->torrent_id;
        $torrent = Torrent::query()->findOrFail($torrentId, Torrent::$commentFields);
        $torrent->checkIsNormal();

        $result = $this->thankService->thankTorrent($user, $torrent);
        $resource = new ThankResource($result);

        return $this->success($resource, '说谢谢成功！');
    }

    /**
     * Display the specified resource.
     *
     * @param  mixed  $id
     */
    public function show($id): Response
    {
        //

        abort(501, 'Not implemented');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  mixed  $id
     */
    public function update(Request $request, $id): Response
    {
        //

        abort(501, 'Not implemented');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  mixed  $id
     */
    public function destroy($id): Response
    {
        //

        abort(501, 'Not implemented');
    }
}
