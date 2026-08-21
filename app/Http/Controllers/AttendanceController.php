<?php

namespace App\Http\Controllers;

use App\Repositories\AttendanceRepository;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    /** @var mixed */
    private $repository;

    /**
     * @return mixed
     */
    public function __construct(AttendanceRepository $repository)
    {
        $this->repository = $repository;
    }

    /** @return  array<string, mixed> */
    public function attend()
    {
        $uid = Auth::id();
        $attendance = $this->repository->attend($uid);

        return $this->success($attendance->toArray());
    }
}
