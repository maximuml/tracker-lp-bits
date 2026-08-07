<?php

namespace App\Http\Controllers;

use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShoutboxController extends LegacyController
{
    public function shoutbox(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'shoutbox', false);
    }

    public function shoutboxHistory(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'shoutbox_history', true);
    }

    public function shoutboxSse(Request $request): SymfonyResponse
    {
        if (SupportContext::getUser() === null) {
            return new SymfonyResponse('', 403);
        }

        $context = SupportContext::getGlobalsForView();

        $callback = function () use ($context) {
            extract($context, EXTR_SKIP);
            $scriptFile = resource_path('views/shoutbox_sse/_shoutbox_sse_legacy.php');
            if (is_file($scriptFile)) {
                require $scriptFile;
            }
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
