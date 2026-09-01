<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PollStoreRequest;
use App\Http\Requests\PollUpdateRequest;
use App\Http\Requests\PollVoteRequest;
use App\Http\Resources\PollResource;
use App\Models\Poll;
use App\Repositories\IndexRepository;
use App\Repositories\PollRepository;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Pagination;
use App\Support\Strings;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PollController extends LegacyController
{
    public function makepoll(Request $request): Response|RedirectResponse|View
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $action = (string) $request->input('action', '');
        $pollid = (int) $request->input('pollid', 0);
        $poll = [];

        if ($action === 'edit') {
            if ($pollid <= 0) {
                return $this->legacyAbortResponse('Error', 'Invalid poll id.');
            }
            $poll = app(PollRepository::class)->findForEdit($pollid);
            if (! $poll) {
                return $this->legacyAbortResponse('Error', 'No poll with that ID.');
            }
        }

        if ($request->isMethod('post')) {
            $pollid = (int) $request->input('pollid', 0);
            $question = htmlspecialchars((string) $request->input('question', ''));
            $returnto = htmlspecialchars((string) $request->input('returnto', ''));

            $options = [];
            for ($i = 0; $i <= 19; $i++) {
                $options["option{$i}"] = htmlspecialchars((string) $request->input("option{$i}", ''));
            }

            if ($question === '' || $options['option0'] === '' || $options['option1'] === '') {
                return $this->legacyAbortResponse('Error', 'Missing form data.');
            }

            $data = array_merge(['question' => $question], $options);
            $newId = app(PollRepository::class)->createOrUpdate($data, $pollid > 0 ? $pollid : null);

            if ($returnto === 'main') {
                return redirect(url('/'));
            } elseif ($pollid > 0) {
                return redirect('/log.php?action=poll#'.$newId);
            }

            return redirect('/');
        }

        $ageWarning = '';
        if ($pollid <= 0) {
            $lastPoll = app(PollRepository::class)->lastPoll();
            if (! empty($lastPoll)) {
                $hours = (int) floor((time() - strtotime((string) $lastPoll['added'])) / 3600);
                $days = (int) floor($hours / 24);
                $lang = (array) (app(Globals::class)->get('lang_makepoll') ?? []);
                if ($days >= 1) {
                    $t = $days.($lang['text_day'] ?? ' day').Strings::addS($days);
                } else {
                    $t = $hours.($lang['text_hour'] ?? ' hour').Strings::addS($hours);
                }
                $ageWarning = ($lang['text_current_poll'] ?? 'Current poll ').'(<i>'.htmlspecialchars((string) $lastPoll['question']).'</i>)'.($lang['text_is_only'] ?? ' is only ').$t.($lang['text_old'] ?? ' old.');
            }
        }

        return $this->legacyPage($request, 'makepoll', true, [
            'poll' => $poll,
            'pollid' => $poll['id'] ?? $pollid,
            'returnto' => htmlspecialchars((string) ($request->input('returnto') ?? $request->headers->get('referer') ?? '')),
            'ageWarning' => $ageWarning,
        ]);
    }

    public function polloverview(Request $request): View|RedirectResponse|Response
    {
        $pollid = (int) $request->input('id', 0);

        if ($pollid > 0) {
            $poll = app(PollRepository::class)->findWithOptions($pollid);
            if (! $poll) {
                $lang = (array) (app(Globals::class)->get('lang_polloverview') ?? []);

                return $this->legacyAbortResponse($lang['std_error'] ?? 'Error', $lang['text_no_poll_id'] ?? 'Invalid poll ID.');
            }

            $count = app(PollRepository::class)->countAnswers($pollid);
            $answers = [];
            $pagertop = '';
            $pagerbottom = '';
            $userDisplayMap = [];

            if ($count > 0) {
                $perpage = 100;
                [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $count, "?id={$pollid}&");
                $answers = app(PollRepository::class)->answers($pollid, $offset, $perpage);
                $userDisplayMap = app(PollRepository::class)->userDisplayMap($answers);
            }

            return $this->legacyPage($request, 'polloverview', true, [
                'mode' => 'detail',
                'poll' => $poll,
                'count' => $count,
                'answers' => $answers,
                'pagertop' => $pagertop,
                'pagerbottom' => $pagerbottom,
                'userDisplayMap' => $userDisplayMap,
            ]);
        }

        $polls = app(PollRepository::class)->listAll();

        return $this->legacyPage($request, 'polloverview', true, [
            'mode' => 'list',
            'polls' => $polls,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $perPage = (int) $request->input('limit', 20);

        $polls = Poll::query()->withCount('answers')->latest('id')->paginate($perPage);

        return $this->success(PollResource::collection($polls));
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Poll $poll): array
    {
        $poll->loadCount('answers');

        return $this->success(new PollResource($poll));
    }

    /**
     * @return array<string, mixed>
     */
    public function store(PollStoreRequest $request): array
    {
        $data = $request->validated();

        $data['added'] = now()->toDateTimeString();

        $poll = Poll::query()->create($data);

        return $this->success(new PollResource($poll), 'Poll created');
    }

    /**
     * @return array<string, mixed>
     */
    public function update(PollUpdateRequest $request, Poll $poll): array
    {
        $data = $request->validated();

        $poll->update($data);

        $fresh = $poll->fresh();
        $fresh?->loadCount('answers');

        return $this->success($fresh ? new PollResource($fresh) : null, 'Poll updated');
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(Poll $poll): array
    {
        $poll->delete();

        return $this->success(['success' => true], 'Poll deleted');
    }

    /**
     * @return array<string, mixed>
     */
    public function latest(): array
    {
        $pollArr = app(IndexRepository::class)->getCurrentPoll();

        if ($pollArr === null) {
            return $this->success([], 'No poll');
        }

        $poll = Poll::query()->withCount('answers')->find($pollArr['id']);

        return $this->success($poll ? new PollResource($poll) : null);
    }

    /**
     * @return array<string, mixed>
     */
    public function vote(PollVoteRequest $request): array
    {
        $currentUser = (array) (app(CurrentUser::class)->get() ?? []);
        $userId = (int) ($currentUser['id'] ?? 0);

        $data = $request->validated();

        $pollId = (int) $data['poll_id'];
        $choice = (int) $data['choice'];

        $poll = Poll::query()->find($pollId);
        if (! $poll) {
            return $this->fail([], 'Poll not found');
        }

        if (empty($poll->getAttribute("option{$choice}"))) {
            return $this->fail([], 'Invalid poll choice');
        }

        if (app(IndexRepository::class)->hasVoted($pollId, $userId)) {
            return $this->fail([], 'Already voted');
        }

        app(IndexRepository::class)->recordPollVote($pollId, $userId, $choice);

        return $this->success(['success' => true], 'Vote recorded');
    }
}
