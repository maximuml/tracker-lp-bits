<?php

namespace App\Http\Controllers;

use App\Http\Resources\ForumResource;
use App\Http\Resources\TopicResource;
use App\Models\Forum;
use App\Models\OverForum;
use App\Models\Topic;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return array
     */
    public function index(Request $request)
    {
        $forumId = $request->forum_id;
        $query = Topic::query()
            ->orderBy("sticky", "desc")
            ->with("user", "firstPost", "lastPost")
        ;
        if ($forumId) {
            $query->where("forumid", $forumId);
        }
        $list = $query->get();
        $resource = TopicResource::collection($list);
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
