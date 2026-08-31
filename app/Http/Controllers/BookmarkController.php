<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\BookmarkRequest;
use App\Http\Resources\BookmarkResource;
use App\Models\User;
use App\Repositories\BookmarkRepository;
use App\Support\Locale;
use Illuminate\Support\Facades\Auth;

class BookmarkController extends Controller
{
    private BookmarkRepository $repository;

    public function __construct(BookmarkRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array<string, mixed>
     */
    public function store(BookmarkRequest $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }
        $result = $this->repository->add($user, $request->torrent_id);
        $resource = new BookmarkResource($result);

        return $this->success($resource, Locale::trans('bookmark.actions.store_success', [], null));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return array<string, mixed>
     */
    public function destroy(BookmarkRequest $request): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }
        $result = $this->repository->remove($user, $request->torrent_id);

        return $this->success(true, Locale::trans('bookmark.actions.delete_success', [], null));
    }
}
