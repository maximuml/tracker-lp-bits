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
     * Store a newly created resource in storage.
     */
    public function store(Request $request): Response
    {
        //

        abort(501, 'Not implemented');
    }

    /**
     * Display the specified resource.
     */
    public function show(OverForum $overForum): Response
    {
        //

        abort(501, 'Not implemented');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, OverForum $overForum): Response
    {
        //

        abort(501, 'Not implemented');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OverForum $overForum): Response
    {
        //

        abort(501, 'Not implemented');
    }
}
