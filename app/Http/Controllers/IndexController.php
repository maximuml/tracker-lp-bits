<?php

namespace App\Http\Controllers;

use App\Repositories\IndexRepository;
use App\Services\Legacy\LegacyPartialRenderer;
use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndexController extends Controller
{
    private LegacyPartialRenderer $renderer;

    public function __construct(LegacyPartialRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function legacy(Request $request): View|RedirectResponse
    {
        if (SupportContext::getUser() === null) {
            $qs = $request->getQueryString();
            return redirect('/index.php' . ($qs ? '?' . $qs : ''));
        }

        if ($request->isMethod('post') && SupportContext::getGlobal('showpolls_main', '') === 'yes') {
            return $this->handlePollVote($request);
        }

        $result = $this->renderer->render('index');
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        return view('index.index', $result);
    }

    private function handlePollVote(Request $request): RedirectResponse
    {
        $choice = $request->input('choice');
        $user = SupportContext::getUser();

        if ($choice === null || $choice === '' || $choice >= 256 || $choice != floor($choice)) {
            return redirect('/index.php');
        }

        $poll = IndexRepository::getCurrentPoll();
        if (! $poll) {
            return redirect('/index.php');
        }

        $pollId = $poll['id'];

        if (IndexRepository::hasVoted($pollId, $user['id'])) {
            return redirect('/index.php');
        }

        IndexRepository::recordPollVote($pollId, $user['id'], (int) $choice);

        $cache = SupportContext::getCache();
        $cache->delete_value('current_poll_content');
        $cache->delete_value('current_poll_result', true);

        $pollvoteBonus = (float) SupportContext::getGlobal('pollvote_bonus', 0);
        if ($pollvoteBonus > 0) {
            \App\Support\Bonus::updatePoints((string) '+', (float) $pollvoteBonus, $user['id']);
        }

        return redirect('/');
    }
}
