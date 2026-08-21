<?php

namespace App\Http\Controllers;

use App\Http\Resources\PeerResource;
use App\Models\Peer;
use App\Repositories\TorrentRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PeerController extends Controller
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
     * Display a listing of the resource.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request)
    {
        $request->validate([
            'torrent_id' => 'required',
        ]);

        $response = [
            'seeder_list' => [],
            'leecher_list' => [],
            //            'card_titles' => Peer::$cardTitles,
            //            'page_title' => nexus_trans('peer.index.page_title'),
        ];
        $result = $this->repository->listPeers($request->torrent_id);
        if ($result['seeder_list']->isNotEmpty()) {
            $response['seeder_list'] = PeerResource::collection($result['seeder_list']);
        }
        if ($result['leecher_list']->isNotEmpty()) {
            $response['leecher_list'] = PeerResource::collection($result['leecher_list']);
        }

        return $this->success($response);

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
