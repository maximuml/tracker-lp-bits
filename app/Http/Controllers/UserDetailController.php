<?php

namespace App\Http\Controllers;

use App\Repositories\UserDetailRepository;
use App\Support\LegacyResponse;
use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserDetailController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $id = (int) $request->input('id', 0);

        if ($id <= 0) {
            abort(404);
        }

        if (! defined('IN_NEXUS') || ! IN_NEXUS || SupportContext::getUser() === null) {
            return redirect('/userdetails.php?' . $request->getQueryString());
        }

        $user = UserDetailRepository::getUser($id);
        /** @var array<string, string> $lang */
        $lang = (array) SupportContext::getGlobal('lang_userdetails', []);

        if ($user === null) {
            LegacyResponse::abort(
                $lang['std_error'] ?? 'Error',
                $lang['std_no_such_user'] ?? 'No user with this ID!'
            );
        }

        if (($user['status'] ?? '') === 'pending') {
            LegacyResponse::abort(
                $lang['std_sorry'] ?? 'Sorry',
                $lang['std_user_not_confirmed'] ?? 'This user is not confirmed.'
            );
        }

        return view('user.details', [
            'id' => $id,
            'user' => $user,
            'lang' => $lang,
        ]);
    }
}
