<?php

namespace App\Http\Controllers;

use App\Http\Resources\ForumResource;
use App\Http\Resources\TopicResource;
use App\Models\Forum;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TopicController extends Controller
{
    /**
     * Display a listing of the resource.
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
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
     * Store a newly created resource in storage.
     * @param  \Illuminate\Http\Request  $request
     * @return  array<string, mixed>
     */
    public function store(Request $request)
    {
        $topic = Topic::query()->create($request->validate([
            'forumid' => 'required|integer',
            'subject' => 'required|string|max:255',
            'userid' => 'required|integer',
            'locked' => 'sometimes|boolean',
            'sticky' => 'sometimes|boolean',
            'hlcolor' => 'sometimes|string',
        ]));

        return $this->success(new TopicResource($topic), 'Topic created');
    }

    /**
     * Display the specified resource.
     * @param  \App\Models\Topic  $topic
     * @return  array<string, mixed>
     */
    public function show(Topic $topic)
    {
        return $this->success(new TopicResource($topic->load('user', 'firstPost', 'lastPost')));
    }

    /**
     * Update the specified resource in storage.
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Topic  $topic
     * @return  array<string, mixed>
     */
    public function update(Request $request, Topic $topic)
    {
        $topic->update($request->validate([
            'forumid' => 'sometimes|integer',
            'subject' => 'sometimes|string|max:255',
            'locked' => 'sometimes|boolean',
            'sticky' => 'sometimes|boolean',
            'hlcolor' => 'sometimes|string',
        ]));

        return $this->success(new TopicResource($topic->fresh()->load('user', 'firstPost', 'lastPost')), 'Topic updated');
    }

    /**
     * Remove the specified resource from storage.
     * @param  \App\Models\Topic  $topic
     * @return  array<string, mixed>
     */
    public function destroy(Topic $topic)
    {
        $topic->delete();

        return $this->success(['success' => true], 'Topic deleted');
    }
}
