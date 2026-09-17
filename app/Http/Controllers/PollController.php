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
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Html\SafeHtml;
use App\Support\Pagination;
use App\Support\Strings;
use App\Support\Time;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PollController extends LegacyController
{
    private PollRepository $pollRepository;

    private IndexRepository $indexRepository;

    private CurrentUser $currentUser;

    private ?LegacyRedisCache $legacyRedisCache;

    public function __construct(
        PollRepository $pollRepository,
        IndexRepository $indexRepository,
        CurrentUser $currentUser,
        ?LegacyRedisCache $legacyRedisCache,
    ) {
        $this->pollRepository = $pollRepository;
        $this->indexRepository = $indexRepository;
        $this->currentUser = $currentUser;
        $this->legacyRedisCache = $legacyRedisCache;
    }

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
            $poll = $this->pollRepository->findForEdit($pollid);
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
            $newId = $this->pollRepository->createOrUpdate($data, $pollid > 0 ? $pollid : null);

            if ($returnto === 'main') {
                return redirect(url('/'));
            } elseif ($pollid > 0) {
                return redirect('/log.php?action=poll#'.$newId);
            }

            return redirect('/');
        }

        $ageWarning = '';
        if ($pollid <= 0) {
            $lastPoll = $this->pollRepository->lastPoll();
            if (! empty($lastPoll)) {
                $hours = (int) floor((time() - strtotime((string) $lastPoll['added'])) / 3600);
                $days = (int) floor($hours / 24);
                if ($days >= 1) {
                    $t = $days.(__('legacy/makepoll.text_day')).Strings::addS($days);
                } else {
                    $t = $hours.(__('legacy/makepoll.text_hour')).Strings::addS($hours);
                }
                $ageWarning = (__('legacy/makepoll.text_current_poll')).'(<i>'.htmlspecialchars((string) $lastPoll['question']).'</i>)'.(__('legacy/makepoll.text_is_only')).$t.(__('legacy/makepoll.text_old'));
            }
        }

        $pollid = (int) ($poll['id'] ?? $pollid);

        return $this->legacyPage($request, 'makepoll', true, [
            'poll' => $poll,
            'pollid' => $pollid,
            'returnto' => htmlspecialchars((string) ($request->input('returnto') ?? $request->headers->get('referer') ?? '')),
            'ageWarning' => $ageWarning,
            'title' => $pollid > 0
                ? (__('legacy/makepoll.head_edit_poll'))
                : (__('legacy/makepoll.head_new_poll')),
        ]);
    }

    public function polloverview(Request $request): View|RedirectResponse|Response
    {
        $pollid = (int) $request->input('id', 0);

        if ($pollid > 0) {
            $poll = $this->pollRepository->findWithOptions($pollid);
            if (! $poll) {
                return $this->legacyAbortResponse(__('legacy/polloverview.std_error'), __('legacy/polloverview.text_no_poll_id'));
            }

            $count = $this->pollRepository->countAnswers($pollid);
            $answers = [];
            $pagertop = SafeHtml::fromTrustedHtml('');
            $pagerbottom = SafeHtml::fromTrustedHtml('');

            if ($count > 0) {
                $perpage = 100;
                [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $count, "?id={$pollid}&");
                $answers = $this->pollRepository->answers($pollid, $offset, $perpage);
            }
            $userDisplayMap = $this->pollRepository->userDisplayMap($answers);

            $answerRows = array_map(static function ($answerRow) use ($userDisplayMap) {
                $row = (array) $answerRow;
                $uid = (int) ($row['userid'] ?? 0);
                $row['usernameHtml'] = (string) ($userDisplayMap[$uid] ?? UserDisplay::username($uid));

                return $row;
            }, $answers);

            $pollOptions = [];
            for ($i = 0; $i < 20; $i++) {
                $option = (string) ($poll["option{$i}"] ?? '');
                if ($option !== '') {
                    $pollOptions[] = ['index' => $i, 'text' => $option];
                }
            }

            return $this->legacyPage($request, 'polloverview', true, [
                'mode' => 'detail',
                'poll' => $poll,
                'pollAdded' => (string) Time::format($poll['added'] ?? ''),
                'pollOptions' => $pollOptions,
                'count' => $count,
                'answers' => $answerRows,
                'pagertop' => $pagertop,
                'pagerbottom' => $pagerbottom,
            ]);
        }

        $polls = $this->pollRepository->listAll();
        if (empty($polls)) {
            return $this->legacyAbortResponse(__('legacy/polloverview.std_error'), __('legacy/polloverview.text_no_users_voted'));
        }

        $pollRows = array_map(static function ($pollRow) {
            $row = (array) $pollRow;
            $row['addedHtml'] = (string) Time::format($row['added'] ?? '');

            return $row;
        }, $polls);

        return $this->legacyPage($request, 'polloverview', true, [
            'mode' => 'list',
            'polls' => $pollRows,
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
        $pollArr = $this->indexRepository->getCurrentPoll();

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
        $currentUser = (array) ($this->currentUser->get() ?? []);
        $userId = (int) ($currentUser['id'] ?? 0);

        $data = $request->validated();

        $pollId = (int) $data['poll_id'];
        $choice = (int) $data['choice'];

        $poll = Poll::query()->find($pollId);
        if (! $poll) {
            return $this->fail([], 'Poll not found');
        }

        if ($choice !== 255 && empty($poll->getAttribute("option{$choice}"))) {
            return $this->fail([], 'Invalid poll choice');
        }

        if ($this->indexRepository->hasVoted($pollId, $userId)) {
            return $this->fail([], 'Already voted');
        }

        $this->indexRepository->recordPollVote($pollId, $userId, $choice);

        // Invalidate legacy poll cache so the index page shows fresh results
        // after an API vote — mirrors IndexController::handlePollVote().
        if ($this->legacyRedisCache !== null) {
            $this->legacyRedisCache->delete_value('current_poll_content');
            $this->legacyRedisCache->delete_value('current_poll_result', true);
        }

        return $this->success(['success' => true], 'Vote recorded');
    }
}
