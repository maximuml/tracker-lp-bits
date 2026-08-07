<?php

namespace App\Http\Controllers;

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
        return $this->legacyPage($request, 'search');
    }

    public function usersearch(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'usersearch');
    }

    public function ajax(Request $request): JsonResponse|RedirectResponse
    {
        if (! defined('IN_NEXUS') || ! IN_NEXUS) {
            $qs = $request->getQueryString();

            return redirect('/ajax.php' . ($qs ? '?' . $qs : ''));
        }

        $action = (string) $request->input('action', '');
        $params = $request->input('params', []);

        $passkeyActions = ['getPasskeyGetArgs', 'processPasskeyGet'];
        if (! in_array($action, $passkeyActions, true)) {
            loggedinorreturn();
        }

        if (! class_exists('AjaxInterface')) {
            view('ajax._ajax_legacy')->render();
        }

        try {
            $callable = ['AjaxInterface', $action];
            if (! is_callable($callable)) {
                $currentUser = SupportContext::getUser() ?? [];
                do_log("hacking attempt made by " . ($currentUser['username'] ?? 'guest') . ",uid " . ($currentUser['id'] ?? 0), 'error');
                throw new \RuntimeException("Invalid action: {$action}");
            }

            $result = call_user_func($callable, $params);

            return response()->json(success($result));
        } catch (\Throwable $exception) {
            do_log($exception->getMessage() . $exception->getTraceAsString(), 'error');

            return response()->json(fail($exception->getMessage(), $request->all()));
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
