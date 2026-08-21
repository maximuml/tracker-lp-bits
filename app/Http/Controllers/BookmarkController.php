<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookmarkResource;
use App\Repositories\BookmarkRepository;
use App\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class BookmarkController extends Controller
{
    /** @var mixed */
    private $repository;

    /**
     * @return mixed
     */
    public function __construct(BookmarkRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return mixed
     */
    public function index(Request $request) {}

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(Request $request)
    {
        $request->validate([
            'torrent_id' => 'required|integer',
        ]);
        $result = $this->repository->add(Auth::user(), $request->torrent_id);
        $resource = new BookmarkResource($result);

        return $this->success($resource, Locale::trans('bookmark.actions.store_success', [], null));
    }

    /**
     * Display the specified resource.
     *
     * @param  mixed  $id
     * @return Response
     */
    public function show($id)
    {

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
     * @return array<string, mixed>
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'torrent_id' => 'required|integer',
        ]);
        $result = $this->repository->remove(Auth::user(), $request->torrent_id);

        return $this->success(true, Locale::trans('bookmark.actions.delete_success', [], null));
    }
}
