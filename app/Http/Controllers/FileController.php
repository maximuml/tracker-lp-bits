<?php

namespace App\Http\Controllers;

use App\Http\Resources\FileResource;
use App\Models\File;
use Illuminate\Http\Request;

class FileController extends Controller
{
    /**
     * torrent file list
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function index(Request $request)
    {
        $torrentId = $request->torrent_id;
        $files = File::query()->where('torrent', $torrentId)->get();
        $resource = FileResource::collection($files);
//        $resource->additional([
//            'page_title' => nexus_trans('file.index.page_title'),
//        ]);

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     * @param  \Illuminate\Http\Request  $request
     * @return  \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    
        return new \Illuminate\Http\Response('');
    }

    /**
     * Display the specified resource.
     * @param  mixed  $id
     * @return  \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    
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
     * @param  mixed  $id
     * @return  \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    
        return new \Illuminate\Http\Response('');
    }
}
