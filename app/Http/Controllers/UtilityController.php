<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\SearchPageRepository;
use App\Support\SupportContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class UtilityController extends LegacyController
{
    public function search(Request $request): View|RedirectResponse
    {
        $curUser = SupportContext::getUser() ?? [];
        $currentUser = ! empty($curUser) ? User::query()->find($curUser['id'] ?? 0) : null;
        if ($currentUser === null) {
            $qs = $request->getQueryString();

            return redirect('/search.php' . ($qs ? '?' . $qs : ''));
        }

        $data = SearchPageRepository::dataForSearch($request, $currentUser);

        return $this->legacyPage($request, 'search', true, $data);
    }

    public function usersearch(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'usersearch');
    }

    public function ajax(Request $request): JsonResponse|RedirectResponse
    {
        if (SupportContext::getCache() === null) {
            $qs = $request->getQueryString();

            return redirect('/ajax.php' . ($qs ? '?' . $qs : ''));
        }

        $action = (string) $request->input('action', '');
        $params = $request->input('params', []);

        $passkeyActions = ['getPasskeyGetArgs', 'processPasskeyGet'];
        if (! in_array($action, $passkeyActions, true)) {
            \App\Support\LegacyAuth::requireLoginFromContext();
        }

        if (! class_exists('AjaxInterface')) {
            view('ajax._ajax')->render();
        }

        try {
            $callable = ['AjaxInterface', $action];
            if (! is_callable($callable)) {
                $currentUser = SupportContext::getUser() ?? [];
                \App\Support\Logger::writeWithContext((string) ("hacking attempt made by " . ($currentUser['username'] ?? 'guest') . ",uid " . ($currentUser['id'] ?? 0)), (string) 'error', (bool) false);
                throw new \RuntimeException("Invalid action: {$action}");
            }

            $result = call_user_func($callable, $params);

            return response()->json(\App\Support\Api::successWithContext($result));
        } catch (\Throwable $exception) {
            \App\Support\Logger::writeWithContext((string) ($exception->getMessage() . $exception->getTraceAsString()), (string) 'error', (bool) false);

            return response()->json(\App\Support\Api::failWithContext($exception->getMessage(), $request->all()));
        }
    }

    public function attachment(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'attachment', true);
    }

    public function getattachment(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'getattachment', true);
    }

    public function image(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'image', false);
    }

    public function page(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'page', false);
    }

    public function tags(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'tags', false);
    }

    public function suggest(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'suggest', false);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'preview', true);
    }

    public function moresmilies(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'moresmilies', true);
    }

    public function smilies(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'smilies', true);
    }

    public function opensearch(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'opensearch', false);
    }

    public function confirmemail(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'confirmemail', false);
    }

    public function ok(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'ok', false);
    }
}
