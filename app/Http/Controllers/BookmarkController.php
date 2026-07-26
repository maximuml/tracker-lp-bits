<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookmarkResource;
use App\Http\Resources\TorrentResource;
use App\Models\Torrent;
use App\Repositories\BookmarkRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookmarkController extends Controller
{
    /** @var  mixed */
    private $repository;

    /**
     * @param  \App\Repositories\BookmarkRepository  $repository
     * @return  mixed
     */
    public function __construct(BookmarkRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return  mixed
     */
    public function index(Request $request)
    {
    }

    /**
     * Store a newly created resource in storage.
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function store(Request $request)
    {
        $request->validate([
            'torrent_id' => 'required|integer',
        ]);
        $result = $this->repository->add(Auth::user(), $request->torrent_id);
        $resource = new BookmarkResource($result);
        return $this->success($resource, nexus_trans('bookmark.actions.store_success'));
    }

    /**
     * Display the specified resource.
     * @param  mixed  $id
     * @return  \Illuminate\Http\Response
     */
    public function show($id)
    {
    
        return new \Illuminate\Http\Response('');
    }

    /**
     * Update the specified resource in storage.
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $id
     * @return  \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    
        return new \Illuminate\Http\Response('');
    }

    /**
     * Remove the specified resource from storage.
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'torrent_id' => 'required|integer',
        ]);
        $result = $this->repository->remove(Auth::user(), $request->torrent_id);
        return $this->success(true, nexus_trans('bookmark.actions.delete_success'));
    }

}
