<?php

namespace App\Http\Controllers;

use App\Http\Resources\SnatchResource;
use App\Models\Snatch;
use App\Repositories\TorrentRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SnatchController extends Controller
{
    /** @var mixed */
    private $repository;

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
    public function index(Request $request)
    {
        $request->validate([
            'torrent_id' => 'required',
        ]);
        $snatches = $this->repository->listSnatches($request->torrent_id);
        $resource = SnatchResource::collection($snatches);
        //        $resource->additional([
        //            'card_titles' => Snatch::$cardTitles,
        //            'page_title' => nexus_trans('snatch.index.page_title'),
        //        ]);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        //

        return new Response('');
    }

    /**
     * Display the specified resource.
     *
     * @param  mixed  $id
     * @return Response
     */
    public function show($id)
    {
        //

        return new Response('');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  mixed  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //

        return new Response('');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  mixed  $id
     * @return Response
     */
    public function destroy($id)
    {
        //

        return new Response('');
    }
}
