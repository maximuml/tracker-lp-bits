<?php

namespace App\Http\Controllers;

use App\Http\Resources\FileResource;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FileController extends Controller
{
    /**
     * torrent file list
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $torrentId = $request->torrent_id;
        $files = File::query()->where('torrent', $torrentId)->get();
        $resource = FileResource::collection($files);

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
