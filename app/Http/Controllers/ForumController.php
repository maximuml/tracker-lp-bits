<?php

namespace App\Http\Controllers;

use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ForumController extends LegacyController
{
    /**
     * Serve the legacy forums.php page from a Laravel view.
     *
     * The real markup lives in resources/views/forum/_forums_legacy.php
     * and is included by forum/index.blade.php so the original HTML/PHP
     * interleaving is preserved as closely as possible.
     */
    public function legacy(Request $request): View|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            return redirect('/forums.php?' . $request->getQueryString());
        }

        return view('forum.index');
    }

    public function forummanage(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'forummanage');
    }

    public function moforums(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'moforums');
    }

    public function latestcomments(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'latestcomments', true);
    }
}
