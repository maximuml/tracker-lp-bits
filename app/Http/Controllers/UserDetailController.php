<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            $currentUser = SupportContext::getUser();
            $id = (int) ($currentUser['id'] ?? 0);
            if ($id <= 0) {
                abort(404);
            }
        }

        if (SupportContext::getUser() === null) {
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

        $userModel = UserDetailRepository::getUserWithMedals($id);
        $temporaryInviteCount = $userModel instanceof User ? UserDetailRepository::getTemporaryInviteCount($userModel) : 0;

        return view('user.details', [
            'id' => $id,
            'user' => $user,
            'lang' => $lang,
            'userModel' => $userModel,
            'torrentcomments' => UserDetailRepository::getCommentCount($id),
            'forumposts' => UserDetailRepository::getPostCount($id),
            'temporaryInviteCount' => $temporaryInviteCount,
            'modcomment' => UserDetailRepository::getModComment($id),
            'bonuscomment' => UserDetailRepository::getBonusComment($id),
        ]);
    }
}
