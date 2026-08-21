<?php

namespace App\Http\Controllers;

use App\Http\Resources\OverForumResource;
use App\Models\OverForum;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OverForumController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return array<string, mixed>
     */
    public function index()
    {
        $list = OverForum::query()->orderBy('sort', 'asc')->get();
        $resource = OverForumResource::collection($list);

        return $this->success($resource);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //

        return new Response('');
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
     * @return Response
     */
    public function show(OverForum $overForum)
    {
        //

        return new Response('');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit(OverForum $overForum)
    {
        //

        return new Response('');
    }

    /**
     * Update the specified resource in storage.
     *
     * @return Response
     */
    public function update(Request $request, OverForum $overForum)
    {
        //

        return new Response('');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy(OverForum $overForum)
    {
        //

        return new Response('');
    }
}
