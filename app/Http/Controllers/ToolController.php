<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\ToolRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ToolController extends Controller
{
    private ToolRepository $repository;

    public function __construct(ToolRepository $repository)
    {
        $this->repository = $repository;
    }

    /** @return  array<int|string, mixed> */
    public function notifications(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('unauthenticated');
        }
        $result = $this->repository->getNotificationCount($user);

        return $this->success($result);
    }

    public function error(Request $request): View
    {
        return view('error', ['error' => $request->query('error')]);
    }
}
