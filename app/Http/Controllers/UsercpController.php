<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\UsercpRepositoryInterface;
use App\DTOs\Usercp\ForumSettingsDto;
use App\DTOs\Usercp\PersonalSettingsDto;
use App\DTOs\Usercp\SecuritySettingsDto;
use App\DTOs\Usercp\TrackerSettingsDto;
use App\Http\Requests\SecuritySaveRequest;
use App\Http\Requests\UpdateForumSettingsRequest;
use App\Http\Requests\UpdatePersonalSettingsRequest;
use App\Http\Requests\UpdateSecuritySettingsRequest;
use App\Http\Requests\UpdateTrackerSettingsRequest;
use App\Models\User;
use App\Policies\UsercpPolicy;
use App\Services\UsercpPageService;
use App\Support\Cache;
use App\Support\PageResponses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UsercpController extends LegacyController
{
    private UsercpRepositoryInterface $repository;

    private UsercpPageService $pageService;

    public function __construct(
        UsercpRepositoryInterface $repository,
        UsercpPageService $pageService,
        private readonly UsercpPolicy $policy,
    ) {
        $this->repository = $repository;
        $this->pageService = $pageService;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(Request $request): array
    {
        return $this->success($this->repository->settings());
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsPost(Request $request): array
    {
        return $this->success($this->repository->updatePersonal(
            PersonalSettingsDto::fromRequest($request, Auth::user()?->avatar),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function forum(Request $request): array
    {
        return $this->success($this->repository->updateForum(ForumSettingsDto::fromRequest($request)));
    }

    /**
     * @return array<string, mixed>
     */
    public function tracker(Request $request): array
    {
        return $this->success($this->repository->updateTracker(TrackerSettingsDto::fromRequest($request)));
    }

    /**
     * @return array<string, mixed>
     */
    public function security(Request $request): array
    {
        return $this->success($this->repository->updateSecurityApi(SecuritySettingsDto::fromRequest($request)));
    }

    /**
     * Serve the legacy usercp.php page from a Laravel view.
     */
    public function legacy(Request $request): View|Response|RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            $qs = $request->getQueryString();

            return redirect('/usercp'.($qs ? '?'.$qs : ''));
        }

        $action = (string) $request->input('action', '');
        $type = (string) $request->input('type', '');

        $allowedActions = ['personal', 'tracker', 'forum', 'security'];
        if ($action !== '' && ! in_array($action, $allowedActions, true)) {
            PageResponses::abort(
                (string) (__('usercp.std_error')),
                (string) (__('usercp.std_invalid_action'))
            );
        }

        $data = $this->pageService->build($action, $type)->toArray();

        return $this->renderPage($request, 'usercp', true, $data);
    }

    /**
     * Persist the colour-scheme toggle from the chrome userbar.
     * Separate from legacyAction because the full tracker form would
     * clobber unrelated fields on a partial POST.
     */
    public function saveTheme(Request $request): Response
    {
        $theme = $request->validate(['theme' => 'required|in:auto,light,dark'])['theme'];

        /** @var User $user */
        $user = Auth::user();
        $user->theme = $theme;
        $user->save();
        Cache::clearUser($user->id, (string) $user->passkey);

        return response()->noContent();
    }

    public function savePersonal(UpdatePersonalSettingsRequest $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect('/usercp');
        }
        if (! $this->policy->updatePersonal($user, $user)) {
            return redirect('/usercp?action=personal');
        }
        $this->repository->updatePersonal(PersonalSettingsDto::fromRequest($request, $user->avatar));

        return redirect('/usercp?action=personal&type=saved');
    }

    public function saveForum(UpdateForumSettingsRequest $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect('/usercp');
        }
        if (! $this->policy->updateForum($user, $user)) {
            return redirect('/usercp?action=forum');
        }
        $this->repository->updateForum(ForumSettingsDto::fromRequest($request));

        return redirect('/usercp?action=forum&type=saved');
    }

    public function saveTracker(UpdateTrackerSettingsRequest $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect('/usercp');
        }
        if (! $this->policy->updateTracker($user, $user)) {
            return redirect('/usercp?action=tracker');
        }
        $this->repository->updateTracker(TrackerSettingsDto::fromRequest($request));

        return redirect('/usercp?action=tracker&type=saved');
    }

    /**
     * Security settings save — renders the password-confirm step; the
     * confirm form then posts to /web/usercp/security/confirm.
     */
    public function saveSecurity(SecuritySaveRequest $request): View|Response|RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect('/usercp');
        }

        $data = $this->pageService->build('security', 'save')->toArray();

        return $this->renderPage($request, 'usercp', true, $data);
    }

    public function confirmSecurity(UpdateSecuritySettingsRequest $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect('/usercp');
        }
        if (! $this->policy->updateSecurity($user, $user)) {
            return redirect('/usercp?action=security');
        }

        return redirect($this->repository->updateSecurityFromLegacyRequest($request));
    }
}
