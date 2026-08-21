<?php

namespace App\Http\Controllers;

use App\Repositories\ToolRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ToolController extends Controller
{
    /** @var mixed */
    private $repository;

    /**
     * @return mixed
     */
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

    /**
     * @return mixed
     */
    public function error(Request $request)
    {
        return view('error', ['error' => $request->query('error')]);
    }
}
