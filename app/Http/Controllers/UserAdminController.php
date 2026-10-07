<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\BusinessType;
use App\Enums\Permission\PermissionEnum;
use App\Http\Requests\AddUserRequest;
use App\Http\Requests\ResetUserRequest;
use App\Http\Requests\SelfEnableRequest;
use App\Http\Requests\UncoRequest;
use App\Models\Setting;
use App\Repositories\BonusRepository;
use App\Repositories\StaffDirectoryRepository;
use App\Repositories\UserListingRepository;
use App\Services\PermissionChecker;
use App\Support\AssetAppender;
use App\Support\CurrentUser;
use App\Support\Html\SafeHtml;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Logger;
use App\Support\Pagination;
use App\Support\Time;
use App\Support\User;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class UserAdminController extends LegacyController
{
    private UserRepositoryInterface $userRepository;

    private UserModerationRepositoryInterface $userModerationRepository;

    private BonusRepository $bonusRepository;

    public function __construct(private readonly PermissionChecker $permissionChecker, private readonly StaffDirectoryRepository $staffDirectoryRepository,
        UserRepositoryInterface $userRepository,
        UserModerationRepositoryInterface $userModerationRepository,
        BonusRepository $bonusRepository,
        private readonly UserListingRepository $userListingRepository,
        private readonly CurrentUser $currentUser,
    ) {
        $this->userRepository = $userRepository;
        $this->userModerationRepository = $userModerationRepository;
        $this->bonusRepository = $bonusRepository;
    }

    public function users(Request $request): View|RedirectResponse|Response
    {
        if (! $this->permissionChecker->userCan(PermissionEnum::VIEW_USER_LIST->value, false, (int) ($this->currentUser->get()['id'] ?? 0))) {
            return $this->abortResponse('Error', 'Permission denied.');
        }

        $search = trim((string) (request()->query('search') ?? ''));
        $class = (string) (request()->query('class') ?? '-');
        $country = (int) (request()->query('country') ?? 0);
        $letter = trim((string) (request()->query('letter') ?? ''));

        if (strlen($letter) > 1) {
            return $this->abortResponse('Error', 'Invalid letter.');
        }

        if (! User::isValidUserClass($class)) {
            $class = '-';
        }

        $q = '';
        if ($search !== '' && $letter === '') {
            $q = 'search='.rawurlencode($search);
        } elseif ($letter !== '' && str_contains('0abcdefghijklmnopqrstuvwxyz', $letter)) {
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
            if ($c->isEmpty()) {
                break;
            }
            $classOptions[] = ['value' => $i, 'label' => $c, 'selected' => $class !== '-' && $class == $i];
        }

        $countryOptions = [['value' => 0, 'label' => __('users.select_any_country'), 'selected' => $country === 0]];
        foreach ($this->userListingRepository->getCountries() as $ct) {
            $countryOptions[] = ['value' => (int) $ct['id'], 'label' => (string) $ct['name'], 'selected' => $country === (int) $ct['id']];
        }

        $perPage = 50;
        $filters = ['search' => $search, 'class' => $class, 'country' => $country, 'letter' => $letter];
        $count = $this->userListingRepository->countUsers($filters);
        [$pagertop, $pagerbottom, , $offset] = Pagination::pager($perPage, $count, '/web/users?'.$q.($q ? '&' : ''));
        $userRows = $this->userListingRepository->listUsers($filters, (int) $offset, $perPage);

        UserDisplay::preload(array_values(array_map(fn ($arr) => (int) $arr['id'], $userRows)));
        $rows = [];
        foreach ($userRows as $arr) {
            $rows[] = [
                'id' => (int) $arr['id'],
                'username_html' => UserDisplay::username((int) $arr['id']),
                'addedFormatted' => SafeHtml::fromTrustedHtml((string) Time::format($arr['added'], true, false)),
                'lastAccessFormatted' => SafeHtml::fromTrustedHtml((string) Time::format($arr['last_access'], true, false)),
                'class_name' => UserClass::name((int) $arr['class'], false, true, true),
                'country' => (int) $arr['country'],
                'country_flagpic' => (string) ($arr['country_flagpic'] ?? ''),
                'country_name' => (string) ($arr['country_name'] ?? ''),
            ];
        }

        $letterItems = [];
        for ($i = 97; $i < 123; $i++) {
            $l = chr($i);
            $L = chr($i - 32);
            $href = null;
            if ($l !== $letter) {
                $href = "?letter={$l}".($class !== '-' ? "&class={$class}" : '').($country > 0 ? "&country={$country}" : '');
            }
            $letterItems[] = ['label' => $L, 'href' => $href];
        }

        return $this->renderPage($request, 'users', true, [
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
            'letterItems' => $letterItems,
        ]);

    }

    public function reset(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->abortResponse('Error', 'Permission denied, Administrator Only.');
        }

        $curUser = $this->currentUser->get() ?? [];
        $currentUsername = (string) ($this->currentUser->username());

        return $this->resetPage($request, false, '');
    }

    public function resetPost(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/admin/users/reset'.$suffix, 308);
    }

    public function resetSubmit(ResetUserRequest $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->abortResponse('Error', 'Permission denied, Administrator Only.');
        }

        $curUser = $this->currentUser->get() ?? [];
        $currentUsername = (string) ($this->currentUser->username());

        $success = false;
        $message = '';

        $username = trim((string) request()->post('username'));
        $newpassword = trim((string) request()->post('newpassword'));
        $newpasswordagain = trim((string) request()->post('newpasswordagain'));

        if ($username === '' || $newpassword === '' || $newpasswordagain === '') {
            return $this->abortResponse('Error', "Don't leave any fields blank.");
        }

        if ($newpassword !== $newpasswordagain) {
            return $this->abortResponse('Error', "The passwords didn't match! Must've typoed. Try again.");
        }

        if (strlen($newpassword) < 6) {
            return $this->abortResponse('Error', 'Sorry, password is too short (min is 6 chars)');
        }

        $user = $this->userRepository->findByUsername($username);
        if (! $user) {
            return $this->abortResponse('Error', "Sorry, that username doesn't exist.");
        }
        $arr = $user->toArray();

        if (UserDisplay::currentClass() <= (int) ($arr['class'] ?? 0)) {
            $log = "Password Reset For {$username} by {$currentUsername} denied: operator class => ".UserDisplay::currentClass().' is not greater than target user => '.($arr['class'] ?? 0);
            Log::writeWithContext($log);
            Logger::writeWithContext($log, 'alert', false);

            return $this->abortResponse('Error', "Sorry, you don't have enough permission to reset this user's password.");
        }

        $userRep = $this->userRepository;
        try {
            $userRep->resetPassword((int) ($arr['id'] ?? 0), $newpassword, $newpasswordagain);
        } catch (\Exception $e) {
            return $this->abortResponse('Error', $e->getMessage());
        }

        Log::writeWithContext("Password Reset For {$username} by {$currentUsername}");
        $success = true;
        $message = "The password of account <b>{$username}</b> is reset, please inform user of this change.";

        return $this->resetPage($request, $success, $message);
    }

    private function resetPage(Request $request, bool $success, string $message): View|RedirectResponse
    {
        return $this->renderPage($request, 'reset', true, [
            'success' => $success,
            'message' => SafeHtml::fromTrustedHtml($message),
        ]);

    }

    public function selfEnable(Request $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($this->currentUser->id());

        $title = Locale::trans('self-enable.title', [], null);
        $unit = Setting::getSelfEnableBonus();

        $viewData = [
            'title' => $title,
            'unit' => $unit,
            'enabled' => (bool) ($this->currentUser->enabled()),
            'bonus' => (float) ($this->currentUser->seedbonus()),
            'latestBanLog' => null,
            'elapsedDay' => 0,
            'total' => 0,
            'isUserBonusEnough' => false,
            'insufficientMessage' => '',
            't' => [
                'featureDisabled' => Locale::trans('self-enable.feature_disabled', [], null),
                'statusNormal' => Locale::trans('self-enable.enable_status_normal', [], null),
                'noBanInfo' => Locale::trans('self-enable.no_ban_info', [], null),
                'latestBanInfo' => Locale::trans('self-enable.latest_ban_info', [], null),
                'deductPerDay' => Locale::trans('self-enable.deduct_bonus_per_day', ['unit' => number_format($unit)], null),
                'deductTotal' => '',
                'enableDesc' => Locale::trans('self-enable.enable_desc', [], null),
                'enableButton' => Locale::trans('self-enable.enable_button', [], null),
            ],
        ];

        AssetAppender::css('#ban-info td {border: none}', 'header', false);

        if ($unit <= 0) {
            return $this->renderPage($request, 'self-enable', true, $viewData);
        }

        if (($this->currentUser->enabled())) {
            return $this->renderPage($request, 'self-enable', true, $viewData);
        }

        $latestBanLog = $this->userModerationRepository->latestBanLogForUser($currentUserId);
        if (! $latestBanLog) {
            $viewData['latestBanLog'] = null;

            return $this->renderPage($request, 'self-enable', true, $viewData);
        }

        $latestBanLogCreatedAt = $latestBanLog->created_at;
        $elapsedDay = $latestBanLogCreatedAt instanceof Carbon
            ? (int) ceil((time() - $latestBanLogCreatedAt->getTimestamp()) / 86400)
            : 0;
        $total = $unit * $elapsedDay;
        $isUserBonusEnough = (float) ($this->currentUser->seedbonus()) >= $total;
        $insufficientMessage = Locale::trans('self-enable.bonus_not_enough', ['bonus' => $this->currentUser->seedbonus()], null);
        $viewData['t']['deductTotal'] = Locale::trans('self-enable.deduct_bonus_total', ['days' => number_format($elapsedDay), 'total' => number_format($total)], null);

        $viewData['latestBanLog'] = $latestBanLog;
        $viewData['elapsedDay'] = $elapsedDay;
        $viewData['total'] = $total;
        $viewData['isUserBonusEnough'] = $isUserBonusEnough;
        $viewData['insufficientMessage'] = $insufficientMessage;

        return $this->renderPage($request, 'self-enable', true, $viewData);

    }

    public function selfEnablePost(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/admin/users/self-enable'.$suffix, 308);
    }

    public function selfEnableSubmit(SelfEnableRequest $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get() ?? [];
        $currentUserId = (int) ($this->currentUser->id());
        $currentUsername = (string) ($this->currentUser->username());

        $title = Locale::trans('self-enable.title', [], null);
        $unit = Setting::getSelfEnableBonus();

        $viewData = [
            'title' => $title,
            'unit' => $unit,
            'enabled' => (bool) ($this->currentUser->enabled()),
            'bonus' => (float) ($this->currentUser->seedbonus()),
            'latestBanLog' => null,
            'elapsedDay' => 0,
            'total' => 0,
            'isUserBonusEnough' => false,
            'insufficientMessage' => '',
            't' => [
                'featureDisabled' => Locale::trans('self-enable.feature_disabled', [], null),
                'statusNormal' => Locale::trans('self-enable.enable_status_normal', [], null),
                'noBanInfo' => Locale::trans('self-enable.no_ban_info', [], null),
                'latestBanInfo' => Locale::trans('self-enable.latest_ban_info', [], null),
                'deductPerDay' => Locale::trans('self-enable.deduct_bonus_per_day', ['unit' => number_format($unit)], null),
                'deductTotal' => '',
                'enableDesc' => Locale::trans('self-enable.enable_desc', [], null),
                'enableButton' => Locale::trans('self-enable.enable_button', [], null),
            ],
        ];

        AssetAppender::css('#ban-info td {border: none}', 'header', false);

        if ($unit <= 0) {
            return $this->renderPage($request, 'self-enable', true, $viewData);
        }

        if (($this->currentUser->enabled())) {
            return $this->renderPage($request, 'self-enable', true, $viewData);
        }

        $latestBanLog = $this->userModerationRepository->latestBanLogForUser($currentUserId);
        if (! $latestBanLog) {
            $viewData['latestBanLog'] = null;

            return $this->renderPage($request, 'self-enable', true, $viewData);
        }

        $latestBanLogCreatedAt = $latestBanLog->created_at;
        $elapsedDay = $latestBanLogCreatedAt instanceof Carbon
            ? (int) ceil((time() - $latestBanLogCreatedAt->getTimestamp()) / 86400)
            : 0;
        $total = $unit * $elapsedDay;
        $isUserBonusEnough = (float) ($this->currentUser->seedbonus()) >= $total;
        $insufficientMessage = Locale::trans('self-enable.bonus_not_enough', ['bonus' => $this->currentUser->seedbonus()], null);
        $viewData['t']['deductTotal'] = Locale::trans('self-enable.deduct_bonus_total', ['days' => number_format($elapsedDay), 'total' => number_format($total)], null);

        if ($request->post('submit')) {
            if (! $isUserBonusEnough) {
                $viewData['latestBanLog'] = $latestBanLog;
                $viewData['elapsedDay'] = $elapsedDay;
                $viewData['total'] = $total;
                $viewData['isUserBonusEnough'] = false;
                $viewData['insufficientMessage'] = $insufficientMessage;
                $viewData['showError'] = true;

                return $this->renderPage($request, 'self-enable', true, $viewData);
            }

            $userRep = $this->userRepository;
            $bonusRep = $this->bonusRepository;
            $operator = $this->userRepository->findById($currentUserId);
            if ($operator) {
                $bonusRep->consumeUserBonus($currentUserId, $total, BusinessType::SELF_ENABLE->value, $title);
                $this->userModerationRepository->enableUser($operator, $currentUserId, $title);
            }

            return redirect('/web/index');
        }
        $viewData['latestBanLog'] = $latestBanLog;
        $viewData['elapsedDay'] = $elapsedDay;
        $viewData['total'] = $total;
        $viewData['isUserBonusEnough'] = $isUserBonusEnough;
        $viewData['insufficientMessage'] = $insufficientMessage;

        return $this->renderPage($request, 'self-enable', true, $viewData);
    }

    public function unco(Request $request): View|RedirectResponse|Response
    {
        if ($this->currentUser->get() === null) {
            $qs = $request->getQueryString();

            return redirect('/web/unco'.($qs ? '?'.$qs : ''));
        }

        if (UserDisplay::currentClass() < UC_MODERATOR) {
            return $this->abortResponse('Sorry', 'Access denied.');
        }

        $status = $request->query('status');
        if ($status) {
            LegacyResponse::assertId($status, true);
        }

        $rows = $this->staffDirectoryRepository->listPendingOrdered()
            ->map(fn ($user) => $user->getAttributes())
            ->toArray();

        if (empty($rows)) {
            if ($status) {
                return $this->abortResponse('Updated!', 'The user account has been updated.');
            }

            return $this->abortResponse('Ups!', 'Nothing Found...');
        }

        return $this->renderPage($request, 'unco', true, [
            'status' => $status,
            'rows' => $rows,
        ]);
    }

    public function uncoPost(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/admin/users/unco'.$suffix, 308);
    }

    public function uncoSubmit(UncoRequest $request): View|RedirectResponse|Response
    {
        return $this->unco($request);
    }

    public function adduser(Request $request): Response|RedirectResponse|View
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->abortResponse('Error', 'Access denied.');
        }

        return $this->renderPage($request, 'adduser', true);
    }

    public function adduserPost(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/admin/users/add'.$suffix, 308);
    }

    public function adduserSubmit(AddUserRequest $request): Response|RedirectResponse|View
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->abortResponse('Error', 'Access denied.');
        }

        $userRep = $this->userRepository;
        try {
            $newUser = $userRep->store([
                'username' => request()->post('username'),
                'email' => request()->post('email'),
                'password' => request()->post('password'),
                'password_confirmation' => request()->post('password2'),
            ]);
        } catch (\Exception $e) {
            return $this->abortResponse('ERROR', $e->getMessage());
        }

        return redirect('/userdetails?id='.(int) $newUser->id);

    }
}
