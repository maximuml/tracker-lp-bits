<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\Usercp\ForumSettingsDto;
use App\DTOs\Usercp\PersonalSettingsDto;
use App\DTOs\Usercp\SecuritySettingsDto;
use App\DTOs\Usercp\TrackerSettingsDto;
use App\Http\Requests\UpdateForumSettingsRequest;
use App\Http\Requests\UpdatePersonalSettingsRequest;
use App\Http\Requests\UpdateSecuritySettingsRequest;
use App\Http\Requests\UpdateTrackerSettingsRequest;
use App\Models\User;
use App\Policies\UsercpPolicy;
use App\Repositories\UsercpRepository;
use App\Services\UsercpPageService;
use App\Support\Globals;
use App\Support\LegacyResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UsercpController extends LegacyController
{
    private UsercpRepository $repository;

    private UsercpPageService $pageService;

    public function __construct(
        UsercpRepository $repository,
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
        if ($request->isMethod('POST')) {
            return $this->success($this->repository->updatePersonal(PersonalSettingsDto::fromRequest($request)));
        }

        return $this->success($this->repository->settings());
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

            return redirect('/usercp.php'.($qs ? '?'.$qs : ''));
        }

        $action = (string) $request->input('action', '');
        $type = (string) $request->input('type', '');

        $allowedActions = ['personal', 'tracker', 'forum', 'security'];
        if ($action !== '' && ! in_array($action, $allowedActions, true)) {
            $langUsercp = (array) (app(Globals::class)->get('lang_usercp') ?? []);
            LegacyResponse::abort(
                (string) ($langUsercp['std_error'] ?? 'Error'),
                (string) ($langUsercp['std_invalid_action'] ?? 'Invalid action.')
            );
        }

        $data = $this->pageService->build($action, $type)->toArray();

        return $this->legacyPage($request, 'usercp', true, $data);
    }

    public function legacyAction(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect('/usercp.php');
        }

        $action = (string) $request->input('action');
        $type = (string) $request->input('type');

        // W1-05: Validate POST mutations by action/type before delegating
        $rules = match (true) {
            $type === 'save' && $action === 'personal' => (new UpdatePersonalSettingsRequest)->rules(),
            $type === 'save' && $action === 'forum' => (new UpdateForumSettingsRequest)->rules(),
            $type === 'save' && $action === 'tracker' => (new UpdateTrackerSettingsRequest)->rules(),
            $type === 'confirm' && $action === 'security' => (new UpdateSecuritySettingsRequest)->rules(),
            default => null,
        };

        if ($rules !== null) {
            $validator = validator($request->all(), $rules);
            if ($validator->fails()) {
                return redirect('/usercp.php?action='.$action);
            }
        }

        if ($type === 'save' && $action === 'personal') {
            if (! $this->policy->updatePersonal($user, $user)) {
                return redirect('/usercp.php?action=personal');
            }
            $this->repository->updatePersonal(PersonalSettingsDto::fromRequest($request));

            return redirect('/usercp.php?action=personal&type=saved');
        }

        if ($type === 'save' && $action === 'forum') {
            if (! $this->policy->updateForum($user, $user)) {
                return redirect('/usercp.php?action=forum');
            }
            $this->repository->updateForum(ForumSettingsDto::fromRequest($request));

            return redirect('/usercp.php?action=forum&type=saved');
        }

        if ($type === 'save' && $action === 'tracker') {
            if (! $this->policy->updateTracker($user, $user)) {
                return redirect('/usercp.php?action=tracker');
            }
            $this->repository->updateTracker(TrackerSettingsDto::fromRequest($request));

            return redirect('/usercp.php?action=tracker&type=saved');
        }

        if ($type === 'confirm' && $action === 'security') {
            if (! $this->policy->updateSecurity($user, $user)) {
                return redirect('/usercp.php?action=security');
            }
            $to = $this->repository->updateSecurityFromLegacyRequest($request);

            return redirect($to);
        }

        return redirect('/usercp.php');
    }
}
