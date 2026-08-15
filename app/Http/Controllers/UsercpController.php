<?php

namespace App\Http\Controllers;

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
            return $this->success($this->repository->updatePersonal($request));
        }

        return $this->success($this->repository->settings());
    }

    /**
     * @return array<string, mixed>
     */
    public function forum(Request $request): array
    {
        return $this->success($this->repository->updateForum($request));
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

        if ($request->isMethod('POST') && $request->input('type') === 'save') {
            $action = (string) $request->input('action');
            if ($action === 'personal') {
                $this->repository->updatePersonal($request);

                return redirect('/usercp.php?action=personal&type=saved');
            }

            if ($action === 'forum') {
                $this->repository->updateForum($request);

                return redirect('/usercp.php?action=forum&type=saved');
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
