<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\BonusLogs;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserBanLog;
use App\Repositories\BonusRepository;
use App\Repositories\UserListingRepository;
use App\Repositories\UserRepository;
use App\Support\CurrentUser;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Logger;
use App\Support\Pagination;
use App\Support\Permissions;
use App\Support\SupportContext;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class UserAdminController extends LegacyController
{
    public function users(Request $request): View|RedirectResponse|Response
    {
        if (! Permissions::userCan(PermissionEnum::VIEW_USER_LIST->value, false, (int) (app(CurrentUser::class)->get()['id'] ?? 0))) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $langUsers = (array) SupportContext::getGlobal('lang_users', []);
        $search = trim((string) (SupportContext::getQuery('search') ?? ''));
        $class = (string) (SupportContext::getQuery('class') ?? '-');
        $country = (int) (SupportContext::getQuery('country') ?? 0);
        $letter = trim((string) (SupportContext::getQuery('letter') ?? ''));

        if (strlen($letter) > 1) {
            return $this->legacyAbortResponse('Error', 'Invalid letter.');
        }

        if (! \App\Support\User::isValidUserClass($class)) {
            $class = '-';
        }

        $q = '';
        if ($search !== '' && $letter === '') {
            $q = 'search='.rawurlencode($search);
        } elseif ($letter !== '' && strpos('0abcdefghijklmnopqrstuvwxyz', $letter) !== false) {
            $q = "letter={$letter}";
        }

        if ($class !== '-') {
            $q .= ($q ? '&' : '')."class={$class}";
        }
        if ($country > 0) {
            $q .= ($q ? '&' : '')."country={$country}";
        }

        $classOptions = [];
        for ($i = 0; ; $i++) {
            $c = UserClass::name($i, false, true, true);
            if (! $c) {
                break;
            }
            $classOptions[] = ['value' => $i, 'label' => $c, 'selected' => $class !== '-' && $class == $i];
        }

        $countryOptions = [['value' => 0, 'label' => $langUsers['select_any_country'] ?? 'Any country', 'selected' => $country === 0]];
        foreach (UserListingRepository::getCountries() as $ct) {
            $countryOptions[] = ['value' => (int) $ct['id'], 'label' => (string) $ct['name'], 'selected' => $country === (int) $ct['id']];
        }

        $perPage = 50;
        $filters = ['search' => $search, 'class' => $class, 'country' => $country, 'letter' => $letter];
        $count = UserListingRepository::countUsers($filters);
        [$pagertop, $pagerbottom, , $offset] = Pagination::pager($perPage, $count, 'users.php?'.$q.($q ? '&' : ''));
        $userRows = UserListingRepository::listUsers($filters, (int) $offset, $perPage);

        $rows = [];
        foreach ($userRows as $arr) {
            $rows[] = [
                'id' => (int) $arr['id'],
                'username_html' => UserDisplay::username((int) $arr['id']),
                'added' => $arr['added'],
                'last_access' => $arr['last_access'],
                'class_name' => UserClass::name((int) $arr['class'], false, true, true),
                'country' => $arr['country'],
            ];
        }

        return $this->legacyPage($request, 'users', true, [
            'lang_users' => $langUsers,
            'search' => $search,
            'class' => $class,
            'country' => $country,
            'letter' => $letter,
            'classOptions' => $classOptions,
            'countryOptions' => $countryOptions,
            'pagerParam' => $q,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
        ]);

    }

    public function reset(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Permission denied, Administrator Only.');
        }

        $curUser = app(CurrentUser::class)->get() ?? [];
        $currentUsername = (string) ($curUser['username'] ?? '');

        $success = false;
        $message = '';

        if ($request->isMethod('post')) {
            $username = trim((string) SupportContext::getPost('username'));
            $newpassword = trim((string) SupportContext::getPost('newpassword'));
            $newpasswordagain = trim((string) SupportContext::getPost('newpasswordagain'));

            if ($username === '' || $newpassword === '' || $newpasswordagain === '') {
                return $this->legacyAbortResponse('Error', "Don't leave any fields blank.");
            }

            if ($newpassword !== $newpasswordagain) {
                return $this->legacyAbortResponse('Error', "The passwords didn't match! Must've typoed. Try again.");
            }

            if (strlen($newpassword) < 6) {
                return $this->legacyAbortResponse('Error', 'Sorry, password is too short (min is 6 chars)');
            }

            $user = User::query()->where('username', $username)->first();
            if (! $user) {
                return $this->legacyAbortResponse('Error', "Sorry, that username doesn't exist.");
            }
            $arr = $user->toArray();

            if (UserDisplay::currentClass() <= (int) $arr['class']) {
                $log = "Password Reset For {$username} by {$currentUsername} denied: operator class => ".UserDisplay::currentClass()." is not greater than target user => {$arr['class']}";
                Log::writeWithContext($log);
                Logger::writeWithContext($log, 'alert', false);

                return $this->legacyAbortResponse('Error', "Sorry, you don't have enough permission to reset this user's password.");
            }

            $userRep = new UserRepository;
            try {
                $userRep->resetPassword((int) $arr['id'], $newpassword, $newpasswordagain);
            } catch (\Exception $e) {
                return $this->legacyAbortResponse('Error', $e->getMessage());
            }

            Log::writeWithContext("Password Reset For {$username} by {$currentUsername}");
            $success = true;
            $message = "The password of account <b>{$username}</b> is reset, please inform user of this change.";
        }

        return $this->legacyPage($request, 'reset', true, [
            'success' => $success,
            'message' => $message,
        ]);

    }

    public function selfEnable(Request $request): View|RedirectResponse|Response
    {
        $curUser = app(CurrentUser::class)->get() ?? [];
        $currentUserId = (int) ($curUser['id'] ?? 0);
        $currentUsername = (string) ($curUser['username'] ?? '');

        $title = Locale::trans('self-enable.title', [], null);
        $unit = Setting::getSelfEnableBonus();

        $viewData = [
            'title' => $title,
            'unit' => $unit,
            'enabled' => ($curUser['enabled'] ?? '') === 'yes',
            'bonus' => (float) ($curUser['seedbonus'] ?? 0),
            'latestBanLog' => null,
            'elapsedDay' => 0,
            'total' => 0,
            'isUserBonusEnough' => false,
            'insufficientMessage' => '',
        ];

        if ($unit <= 0) {
            return $this->legacyPage($request, 'self-enable', true, $viewData);
        }

        if (($curUser['enabled'] ?? '') === 'yes') {
            return $this->legacyPage($request, 'self-enable', true, $viewData);
        }

        $latestBanLog = UserBanLog::query()->where('uid', $currentUserId)->orderByDesc('id')->first();
        if (! $latestBanLog) {
            $viewData['latestBanLog'] = null;

            return $this->legacyPage($request, 'self-enable', true, $viewData);
        }

        $latestBanLogCreatedAt = $latestBanLog->created_at;
        $elapsedDay = $latestBanLogCreatedAt instanceof Carbon
            ? (int) ceil((time() - $latestBanLogCreatedAt->getTimestamp()) / 86400)
            : 0;
        $total = $unit * $elapsedDay;
        $isUserBonusEnough = (float) ($curUser['seedbonus'] ?? 0) >= $total;
        $insufficientMessage = Locale::trans('self-enable.bonus_not_enough', ['bonus' => $curUser['seedbonus'] ?? 0], null);

        if (! empty(SupportContext::getPost('submit'))) {
            if (! $isUserBonusEnough) {
                $viewData['latestBanLog'] = $latestBanLog;
                $viewData['elapsedDay'] = $elapsedDay;
                $viewData['total'] = $total;
                $viewData['isUserBonusEnough'] = false;
                $viewData['insufficientMessage'] = $insufficientMessage;

                return $this->legacyPage($request, 'self-enable', true, $viewData);
            }

            $userRep = new UserRepository;
            $bonusRep = new BonusRepository;
            $operator = User::query()->find($currentUserId);
            if ($operator) {
                $bonusRep->consumeUserBonus($currentUserId, $total, BonusLogs::BUSINESS_TYPE_SELF_ENABLE, $title);
                $userRep->enableUser($operator, $currentUserId, $title);
            }

            return redirect('index.php');
        }

        $viewData['latestBanLog'] = $latestBanLog;
        $viewData['elapsedDay'] = $elapsedDay;
        $viewData['total'] = $total;
        $viewData['isUserBonusEnough'] = $isUserBonusEnough;
        $viewData['insufficientMessage'] = $insufficientMessage;

        return $this->legacyPage($request, 'self-enable', true, $viewData);

    }

    public function unco(Request $request): View|RedirectResponse|Response
    {
        if (app(CurrentUser::class)->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/unco.php'.($qs ? '?'.$qs : ''));
        }

        $status = SupportContext::getQuery('status');
        if ($status) {
            LegacyResponse::assertId($status, true);
        }

        $rows = User::query()
            ->where('status', 'pending')
            ->orderBy('username')
            ->get()
            ->map(fn ($user) => $user->getAttributes())
            ->toArray();

        return $this->legacyPage($request, 'unco', true, [
            'status' => $status,
            'rows' => $rows,
        ]);
    }

    public function adduser(Request $request): Response|RedirectResponse|View
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Access denied.');
        }

        if ($request->isMethod('post')) {
            $userRep = new UserRepository;
            try {
                $newUser = $userRep->store([
                    'username' => SupportContext::getPost('username'),
                    'email' => SupportContext::getPost('email'),
                    'password' => SupportContext::getPost('password'),
                    'password_confirmation' => SupportContext::getPost('password2'),
                ]);
            } catch (\Exception $e) {
                return $this->legacyAbortResponse('ERROR', $e->getMessage());
            }

            return redirect('userdetails.php?id='.(int) $newUser->id);
        }

        return $this->legacyPage($request, 'adduser', true);

    }
}
