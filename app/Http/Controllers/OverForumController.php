<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\OverForumResource;
use App\Repositories\OverforumRepository;

class OverForumController extends Controller
{
    public function __construct(private readonly OverforumRepository $overforumRepository) {}

    /**
     * Display a listing of the resource.
     *
     * @return array<string, mixed>
     */
    public function index(): array
    {
        $list = $this->overforumRepository->listOrdered();
        $resource = OverForumResource::collection($list);

        return $this->success($resource);
    }
}
