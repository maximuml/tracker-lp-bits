<?php

namespace App\Http\Controllers;

use App\Repositories\PollRepository;
use App\Support\Pagination;
use App\Support\Strings;
use App\Support\SupportContext;
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
            $poll = PollRepository::findForEdit($pollid);
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
            $newId = PollRepository::createOrUpdate($data, $pollid > 0 ? $pollid : null);

            if ($returnto === 'main') {
                return redirect(url('/'));
            } elseif ($pollid > 0) {
                return redirect('/log.php?action=poll#'.$newId);
            }

            return redirect('/');
        }

        $ageWarning = '';
        if ($pollid <= 0) {
            $lastPoll = PollRepository::lastPoll();
            if (! empty($lastPoll)) {
                $hours = (int) floor((time() - strtotime((string) $lastPoll['added'])) / 3600);
                $days = (int) floor($hours / 24);
                $lang = (array) (SupportContext::getGlobal('lang_makepoll') ?? []);
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
            $poll = PollRepository::findWithOptions($pollid);
            if (! $poll) {
                $lang = (array) (SupportContext::getGlobal('lang_polloverview') ?? []);

                return $this->legacyAbortResponse($lang['std_error'] ?? 'Error', $lang['text_no_poll_id'] ?? 'Invalid poll ID.');
            }

            $count = PollRepository::countAnswers($pollid);
            $answers = [];
            $pagertop = '';
            $pagerbottom = '';
            $userDisplayMap = [];

            if ($count > 0) {
                $perpage = 100;
                [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $count, "?id={$pollid}&");
                $answers = PollRepository::answers($pollid, $offset, $perpage);
                $userDisplayMap = PollRepository::userDisplayMap($answers);
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

        $polls = PollRepository::listAll();

        return $this->legacyPage($request, 'polloverview', true, [
            'mode' => 'list',
            'polls' => $polls,
        ]);
    }
}
