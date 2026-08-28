<?php

declare(strict_types=1);

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
    public function index(): array
    {
        $list = OverForum::query()->orderBy('sort', 'asc')->get();
        $resource = OverForumResource::collection($list);

        return $this->success($resource);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        //

        return new Response('');
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
     */
    public function show(OverForum $overForum): Response
    {
        //

        return new Response('');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(OverForum $overForum): Response
    {
        //

        return new Response('');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, OverForum $overForum): Response
    {
        //

        return new Response('');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OverForum $overForum): Response
    {
        //

        return new Response('');
    }
}
