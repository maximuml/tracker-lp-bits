<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Poll;
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
        private readonly CurrentUser $currentUser,
        private readonly Globals $globals,
        private readonly IndexRepository $indexRepository,
        private readonly ?LegacyRedisCache $legacyRedisCache,
    ) {}

    public function legacy(Request $request): View|Response|RedirectResponse
    {
        $user = $this->currentUser->get();
        if ($user === null) {
            $qs = $request->getQueryString();

            return redirect('/index.php'.($qs ? '?'.$qs : ''));
        }

        $this->indexRepository->touchLastHome((int) $user['id']);

        if ($request->isMethod('post') && $this->globals->get('showpolls_main', '') === 'yes') {
            return $this->handlePollVote($request);
        }

        $data = $this->indexPageService->build()->toArray();

        return view('index.index', $data);
    }

    private function handlePollVote(Request $request): RedirectResponse
    {
        $choice = $request->input('choice');
        $user = $this->currentUser->get();

        if ($choice === null || $choice === '' || (int) $choice != floor((float) $choice)) {
            return redirect('/index.php');
        }

        $choiceInt = (int) $choice;
        // Allow 0-19 for normal options, 255 for blank vote.
        if ($choiceInt < 0 || ($choiceInt > Poll::MAX_OPTION_INDEX && $choiceInt !== 255)) {
            return redirect('/index.php');
        }

        $poll = $this->indexRepository->getCurrentPoll();
        if (! is_array($poll) || ! isset($poll['id']) || ! is_array($user)) {
            return redirect('/index.php');
        }

        $pollId = $poll['id'];

        $optionKey = "option{$choiceInt}";
        if ($choiceInt !== 255 && empty($poll[$optionKey])) {
            return redirect('/index.php');
        }

        if ($this->indexRepository->hasVoted($pollId, $user['id'])) {
            return redirect('/index.php');
        }

        $this->indexRepository->recordPollVote($pollId, $user['id'], $choiceInt);

        $cache = $this->legacyRedisCache;
        if ($cache !== null) {
            $cache->delete_value('current_poll_content');
            $cache->delete_value('current_poll_result', true);
        }

        $pollvoteBonus = (float) $this->globals->get('pollvote_bonus', 0);
        if ($pollvoteBonus > 0) {
            Bonus::updatePoints((string) '+', (float) $pollvoteBonus, $user['id']);
        }

        return redirect('/');
    }
}
