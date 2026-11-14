<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Enums\UserStatus;
use App\Models\User;
use App\Repositories\UserDetailRepository;
use App\Services\UserDetailPageService;
use App\Support\PageResponses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserDetailController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserDetailRepository $userDetailRepository,
        private readonly UserDetailPageService $userDetailPageService,
        private readonly ActorContext $actor,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $id = (int) $request->input('id', 0);

        if ($id <= 0) {
            $id = (int) ($this->actor->id);
            if ($id <= 0) {
                abort(404);
            }
        }

        if ($this->actor->isGuest()) {
            return redirect('/userdetails?'.$request->getQueryString());
        }

        $user = $this->userDetailRepository->getUser($id);

        if ($user === null) {
            PageResponses::abort(
                __('userdetails.std_error'),
                __('userdetails.std_no_such_user')
            );
        }

        if (($user['status'] ?? null) === UserStatus::PENDING->stringValue()) {
            PageResponses::abort(
                __('userdetails.std_sorry'),
                __('userdetails.std_user_not_confirmed')
            );
        }

        $userModel = $this->userRepository->findById($id);

        $vm = $this->userDetailPageService->build($id, $user, $userModel instanceof User ? $userModel : null);

        return view('user.details', ['vm' => $vm]);
    }
}
