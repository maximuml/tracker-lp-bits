<?php

namespace App\Http\Controllers;

use App\Repositories\ToolRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ToolController extends Controller
{
    /** @var mixed */
    private $repository;

    public function __construct(ToolRepository $repository)
    {
        $this->repository = $repository;
    }

    /** @return  array<int|string, mixed> */
    public function notifications(): array
    {
        $user = Auth::user();
        $result = $this->repository->getNotificationCount($user);

        return $this->success($result);
    }

    public function error(Request $request): View
    {
        return view('error', ['error' => $request->query('error')]);
    }
}
