<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\SnatchResource;
use App\Repositories\TorrentRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SnatchController extends Controller
{
    private TorrentRepository $repository;

    /**
     * @return mixed
     */
    public function __construct(TorrentRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $request->validate([
            'torrent_id' => 'required',
        ]);
        $snatches = $this->repository->listSnatches($request->torrent_id);
        $resource = SnatchResource::collection($snatches);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): Response
    {
        //

        return new Response('');
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
