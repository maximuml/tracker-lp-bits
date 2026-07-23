<?php

namespace App\Http\Controllers;

use App\Http\Resources\ForumResource;
use App\Models\Forum;
use App\Models\OverForum;
use Illuminate\Http\Request;

class ForumController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return array
     */
    public function index(Request $request)
    {
        $forId = $request->forid;
        $query = Forum::query()->orderBy("sort", "asc")->with("moderators");
        if ($forId) {
            $query->where("forid", $forId);
        }
        $list = $query->get();
        $resource = ForumResource::collection($list);
        return $this->success($resource);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    
        return new \Illuminate\Http\Response('');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    
        return new \Illuminate\Http\Response('');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\OverForum  $overForum
     * @return \Illuminate\Http\Response
     */
    public function show(OverForum $overForum)
    {
        //
    
        return new \Illuminate\Http\Response('');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\OverForum  $overForum
     * @return \Illuminate\Http\Response
     */
    public function edit(OverForum $overForum)
    {
        //
    
        return new \Illuminate\Http\Response('');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\OverForum  $overForum
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, OverForum $overForum)
    {
        //
    
        return new \Illuminate\Http\Response('');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\OverForum  $overForum
     * @return \Illuminate\Http\Response
     */
    public function destroy(OverForum $overForum)
    {
        //
    
        return new \Illuminate\Http\Response('');
    }
}
