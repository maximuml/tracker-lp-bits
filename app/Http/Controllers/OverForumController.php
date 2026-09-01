<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\OverForumResource;
use App\Models\OverForum;

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
}
