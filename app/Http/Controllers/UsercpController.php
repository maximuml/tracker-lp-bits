<?php

namespace App\Http\Controllers;

use App\DTOs\Usercp\ForumSettingsDto;
use App\DTOs\Usercp\PersonalSettingsDto;
use App\DTOs\Usercp\SecuritySettingsDto;
use App\DTOs\Usercp\TrackerSettingsDto;
use App\Repositories\UsercpRepository;
use App\Services\Legacy\LegacyPartialRenderer;
use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsercpController extends LegacyController
{
    private UsercpRepository $repository;

    private LegacyPartialRenderer $renderer;

    public function __construct(UsercpRepository $repository, LegacyPartialRenderer $renderer)
    {
        $this->repository = $repository;
        $this->renderer = $renderer;
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
    public function legacy(Request $request): View|RedirectResponse
    {
        $user = SupportContext::getUser();
        if ($user === null) {
            $qs = $request->getQueryString();

            return redirect('/usercp.php' . ($qs ? '?' . $qs : ''));
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

        $userInfo = $this->repository->getUserById((int) $user['id']);
        $result = $this->renderer->render('usercp', ['tokens' => $this->repository->getUserTokens($userInfo)]);
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        return $this->legacyPage($request, 'usercp', true, $result);
    }
}
