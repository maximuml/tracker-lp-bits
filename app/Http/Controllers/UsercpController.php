<?php

namespace App\Http\Controllers;

use App\DTOs\Usercp\ForumSettingsDto;
use App\DTOs\Usercp\PersonalSettingsDto;
use App\DTOs\Usercp\SecuritySettingsDto;
use App\DTOs\Usercp\TrackerSettingsDto;
use App\Repositories\UsercpRepository;
use App\Services\UsercpPageService;
use App\Support\CurrentUser;
use App\Support\LegacyResponse;
use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class UsercpController extends LegacyController
{
    private UsercpRepository $repository;

    private UsercpPageService $pageService;

    public function __construct(UsercpRepository $repository, UsercpPageService $pageService)
    {
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
        $user = app(CurrentUser::class)->get();
        if ($user === null) {
            $qs = $request->getQueryString();

            return redirect('/usercp.php'.($qs ? '?'.$qs : ''));
        }

        if ($request->isMethod('POST')) {
            $action = (string) $request->input('action');
            $type = (string) $request->input('type');

            if ($type === 'save' && $action === 'personal') {
                $this->repository->updatePersonal(PersonalSettingsDto::fromRequest($request));

                return redirect('/usercp.php?action=personal&type=saved');
            }

            if ($type === 'save' && $action === 'forum') {
                $this->repository->updateForum(ForumSettingsDto::fromRequest($request));

                return redirect('/usercp.php?action=forum&type=saved');
            }

            if ($type === 'save' && $action === 'tracker') {
                $this->repository->updateTracker(TrackerSettingsDto::fromRequest($request));

                return redirect('/usercp.php?action=tracker&type=saved');
            }

            if ($type === 'confirm' && $action === 'security') {
                $to = $this->repository->updateSecurityFromLegacyRequest($request);

                return redirect($to);
            }
        }

        $action = (string) $request->input('action', '');
        $type = (string) $request->input('type', '');

        $allowedActions = ['personal', 'tracker', 'forum', 'security'];
        if ($action !== '' && ! in_array($action, $allowedActions, true)) {
            $langUsercp = (array) (SupportContext::getGlobal('lang_usercp') ?? []);
            LegacyResponse::abort(
                (string) ($langUsercp['std_error'] ?? 'Error'),
                (string) ($langUsercp['std_invalid_action'] ?? 'Invalid action.')
            );
        }

        $data = $this->pageService->build($action, $type);

        return $this->legacyPage($request, 'usercp', true, $data);
    }
}
