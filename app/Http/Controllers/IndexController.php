<?php

namespace App\Http\Controllers;

use App\Repositories\IndexRepository;
use App\Services\IndexPageService;
use App\Support\Bonus;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class IndexController extends Controller
{
    public function __construct(
        private readonly IndexPageService $indexPageService,
    ) {}

    public function legacy(Request $request): View|Response|RedirectResponse
    {
        $user = app(CurrentUser::class)->get();
        if ($user === null) {
            $qs = $request->getQueryString();

            return redirect('/index.php'.($qs ? '?'.$qs : ''));
        }

        IndexRepository::touchLastHome((int) $user['id']);

        if ($request->isMethod('post') && app(Globals::class)->get('showpolls_main', '') === 'yes') {
            return $this->handlePollVote($request);
        }

        $data = $this->indexPageService->build();

        return view('index.index', $data);
    }

    private function handlePollVote(Request $request): RedirectResponse
    {
        $choice = $request->input('choice');
        $user = app(CurrentUser::class)->get();

        if ($choice === null || $choice === '' || $choice >= 256 || $choice != floor($choice)) {
            return redirect('/index.php');
        }

        $poll = IndexRepository::getCurrentPoll();
        if (! is_array($poll) || ! isset($poll['id']) || ! is_array($user)) {
            return redirect('/index.php');
        }

        $pollId = $poll['id'];

        if (IndexRepository::hasVoted($pollId, $user['id'])) {
            return redirect('/index.php');
        }

        IndexRepository::recordPollVote($pollId, $user['id'], (int) $choice);

        $cache = app(LegacyRedisCache::class);
        if ($cache !== null) {
            $cache->delete_value('current_poll_content');
            $cache->delete_value('current_poll_result', true);
        }

        $pollvoteBonus = (float) app(Globals::class)->get('pollvote_bonus', 0);
        if ($pollvoteBonus > 0) {
            Bonus::updatePoints((string) '+', (float) $pollvoteBonus, $user['id']);
        }

        return redirect('/');
    }
}
