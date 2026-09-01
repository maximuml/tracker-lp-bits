<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Setting;
use App\Repositories\LogRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Pagination;
use App\Support\Time;
use App\Support\UserClass;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class LogController extends LegacyController
{
    public function legacy(Request $request): View|RedirectResponse|Response
    {
        $langLog = (array) (app(Globals::class)->get('lang_log') ?? []);

        if (! Permission::can(PermissionEnum::LOG)) {
            $logClass = (int) app(Globals::class)->get('log_class', 0);

            return $this->legacyAbortResponse(
                $langLog['std_sorry'] ?? 'Sorry',
                ($langLog['std_permission_denied_only'] ?? 'Permission denied. ').UserClass::name($logClass, false, true, true).sprintf($langLog['std_or_above_can_view'] ?? ' or above can view %s.', Setting::getSiteName()),
                false
            );
        }

        $currentUser = (array) (app(CurrentUser::class)->get() ?? []);
        $userId = (int) ($currentUser['id'] ?? 0);

        $action = (string) ($request->input('action', 'dailylog'));
        $allowed = ['dailylog', 'chronicle', 'news', 'poll'];
        if (! in_array($action, $allowed, true)) {
            return $this->legacyAbortResponse($langLog['std_error'] ?? 'Error', $langLog['std_invalid_action'] ?? 'Invalid action.');
        }

        return match ($action) {
            'dailylog' => $this->dailyLog($request, $langLog),
            'chronicle' => $this->chronicle($request, $userId, $langLog),
            'news' => $this->newsLog($request, $langLog),
            'poll' => $this->pollLog($request, $langLog),
        };
    }

    /**
     * @param  array<string, mixed>  $langLog
     */
    private function dailyLog(Request $request, array $langLog): View|RedirectResponse
    {
        $q = htmlspecialchars(trim((string) ($request->input('query') ?? '')));
        $search = (string) ($request->input('search') ?? '');
        $canConfidential = Permission::can(PermissionEnum::CONFIDENTIAL_LOG);

        $filters = ['search' => $search, 'query' => $q];
        $count = app(LogRepository::class)->countSiteLog($filters);

        $perpage = 50;
        $base = '?action=dailylog&'.($search !== '' && $canConfidential ? 'search='.rawurlencode($search).'&' : '').($q !== '' ? 'query='.rawurlencode($q).'&' : '');
        [$pagertop, $pagerbottom, , $offset] = Pagination::pager($perpage, $count, $base);

        $logRows = app(LogRepository::class)->getSiteLog($filters, (int) $offset, $perpage);

        $userIds = array_filter(array_unique(array_column($logRows, 'uid')));
        UserDisplay::preload(array_map('intval', $userIds));
        $userDisplayMap = [];
        foreach ($userIds as $uid) {
            $userDisplayMap[(int) $uid] = (int) $uid > 0 ? UserDisplay::username((int) $uid) : 'System';
        }

        return $this->legacyPage($request, 'log', true, [
            'mode' => 'dailylog',
            'q' => $q,
            'search' => $search,
            'logRows' => $logRows,
            'count' => $count,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'userDisplayMap' => $userDisplayMap,
            'canConfidentialLog' => $canConfidential,
            'title' => $langLog['text_log'] ?? 'Log',
        ]);
    }

    /**
     * @param  array<string, mixed>  $langLog
     */
    private function chronicle(Request $request, int $userId, array $langLog): View|RedirectResponse|Response
    {
        $q = htmlspecialchars(trim((string) ($request->input('query') ?? '')));
        $canManage = Permission::can(PermissionEnum::CHR_MANAGE);

        $do = (string) ($request->input('do') ?? '');

        // 'edit' is read-only (shows the edit form) and safe via GET.
        // 'add', 'update', 'del' are state-changing and require POST.
        if ($do === 'edit') {
            if (! $canManage) {
                return $this->legacyAbortResponse($langLog['std_error'] ?? 'Error', $langLog['std_permission_denied'] ?? 'Permission denied.');
            }
            $id = (int) $request->input('id', 0);
            $editItem = $id > 0 ? app(LogRepository::class)->getChronicleById($id) : null;
            if ($editItem === null) {
                return redirect('/log.php?action=chronicle');
            }

            return $this->chronicleList($request, $q, $canManage, $langLog, $editItem);
        }

        if ($request->isMethod('post') && $do !== '') {
            if (! $canManage) {
                return $this->legacyAbortResponse($langLog['std_error'] ?? 'Error', $langLog['std_permission_denied'] ?? 'Permission denied.');
            }

            if ($do === 'add') {
                $txt = (string) ($request->input('txt') ?? '');
                if ($txt !== '') {
                    app(LogRepository::class)->addChronicle($userId, $txt);
                }

                return redirect('/log.php?action=chronicle');
            }

            if ($do === 'update') {
                $id = (int) $request->input('id', 0);
                $txt = (string) ($request->input('txt') ?? '');
                if ($id <= 0) {
                    return redirect('/log.php?action=chronicle');
                }
                if ($txt !== '') {
                    app(LogRepository::class)->updateChronicle($id, $txt);
                }

                return redirect('/log.php?action=chronicle');
            }

            if ($do === 'del') {
                $id = (int) $request->input('id', 0);
                if ($id <= 0) {
                    return redirect('/log.php?action=chronicle');
                }
                app(LogRepository::class)->deleteChronicle($id);

                return redirect('/log.php?action=chronicle');
            }
        }

        return $this->chronicleList($request, $q, $canManage, $langLog, null);
    }

    /**
     * @param  array<string, mixed>  $langLog
     * @param  array<int|string, mixed>|null  $editItem
     */
    private function chronicleList(Request $request, string $q, bool $canManage, array $langLog, ?array $editItem): View|RedirectResponse
    {
        $count = app(LogRepository::class)->countChronicle($q);
        $perpage = 50;
        $base = '?action=chronicle&'.($q !== '' ? 'query='.rawurlencode($q).'&' : '');
        [$pagertop, $pagerbottom, , $offset] = Pagination::pager($perpage, $count, $base);

        $chronicleRows = app(LogRepository::class)->getChronicle($q, (int) $offset, $perpage);

        return $this->legacyPage($request, 'log', true, [
            'mode' => 'chronicle',
            'q' => $q,
            'chronicleRows' => $chronicleRows,
            'editItem' => $editItem,
            'count' => $count,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'canManage' => $canManage,
            'title' => $langLog['text_chronicle'] ?? 'Chronicle',
        ]);
    }

    /**
     * @param  array<string, mixed>  $langLog
     */
    private function newsLog(Request $request, array $langLog): View|RedirectResponse
    {
        $q = htmlspecialchars(trim((string) ($request->input('query') ?? '')));
        $search = (string) ($request->input('search') ?? '');

        $filters = ['search' => $search, 'query' => $q];
        $count = app(LogRepository::class)->countNews($filters);

        $perpage = 20;
        $base = '?action=news&'.($search !== '' ? 'search='.rawurlencode($search).'&' : '').($q !== '' ? 'query='.rawurlencode($q).'&' : '');
        [$pagertop, $pagerbottom, , $offset] = Pagination::pager($perpage, $count, $base);

        $newsRows = app(LogRepository::class)->getNews($filters, (int) $offset, $perpage);

        return $this->legacyPage($request, 'log', true, [
            'mode' => 'news',
            'q' => $q,
            'search' => $search,
            'newsRows' => $newsRows,
            'count' => $count,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'title' => $langLog['text_news'] ?? 'News log',
        ]);
    }

    /**
     * @param  array<string, mixed>  $langLog
     */
    private function pollLog(Request $request, array $langLog): View|RedirectResponse|Response
    {
        $do = (string) ($request->input('do') ?? '');
        $pollid = (int) $request->input('pollid', 0);
        $returnto = htmlspecialchars((string) ($request->input('returnto') ?? ''));

        if ($do === 'delete') {
            if (! Permission::can(PermissionEnum::POLL_MANAGE)) {
                return $this->legacyAbortResponse($langLog['std_error'] ?? 'Error', $langLog['std_permission_denied'] ?? 'Permission denied.');
            }
            if ($pollid <= 0) {
                return $this->legacyAbortResponse($langLog['std_error'] ?? 'Error', $langLog['std_invalid_poll_id'] ?? 'Invalid poll ID.');
            }
            if ((int) $request->input('sure', 0) !== 1) {
                $confirm = ($langLog['std_delete_poll_confirmation'] ?? 'Are you sure? ')."<a href=\"?action=poll&do=delete&pollid=$pollid&returnto=$returnto&sure=1\">".($langLog['std_here_if_sure'] ?? 'here').'</a>';

                return $this->legacyAbortResponse($langLog['std_delete_poll'] ?? 'Delete poll', $confirm, false);
            }
            app(LogRepository::class)->deletePoll($pollid);

            $cache = app(LegacyRedisCache::class);
            if ($cache !== null) {
                $cache->delete_value('current_poll_content');
                $cache->delete_value('current_poll_result', true);
            }

            if ($returnto === 'main') {
                return redirect('/');
            }

            return redirect('/log.php?action=poll&deleted=1');
        }

        $pollcount = app(LogRepository::class)->getPollCount();
        if ($pollcount === 0) {
            return $this->legacyAbortResponse($langLog['std_sorry'] ?? 'Sorry', $langLog['std_no_polls'] ?? 'No polls.');
        }

        $polls = app(LogRepository::class)->getPollsExceptFirst();
        $pollData = [];
        foreach ($polls as $poll) {
            $options = [];
            for ($i = 0; $i < 20; $i++) {
                $optionText = (string) ($poll["option{$i}"] ?? '');
                if ($optionText !== '') {
                    $options[$i] = $optionText;
                }
            }

            $voteCounts = app(LogRepository::class)->getPollVoteCounts((int) ($poll['id'] ?? 0));
            $totalVotes = array_sum($voteCounts);

            $computedOptions = [];
            foreach ($options as $index => $text) {
                $votes = $voteCounts[$index] ?? 0;
                $percent = $totalVotes > 0 ? round($votes / $totalVotes * 100) : 0;
                $computedOptions[] = [
                    'text' => $text,
                    'votes' => $votes,
                    'percent' => $percent,
                ];
            }

            $pollData[] = [
                'poll' => $poll,
                'added' => Time::format($poll['added'] ?? '', true, false),
                'totalVotes' => number_format($totalVotes),
                'options' => $computedOptions,
            ];
        }

        return $this->legacyPage($request, 'log', true, [
            'mode' => 'poll',
            'pollData' => $pollData,
            'canPollManage' => Permission::can(PermissionEnum::POLL_MANAGE),
            'title' => $langLog['text_poll'] ?? 'Poll archive',
        ]);
    }
}
