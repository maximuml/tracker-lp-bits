<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Http\Requests\ChronicleAddRequest;
use App\Http\Requests\ChronicleDeleteRequest;
use App\Http\Requests\ChronicleUpdateRequest;
use App\Http\Requests\PollDeleteRequest;
use App\Models\Setting;
use App\Repositories\LogRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Html\SafeHtml;
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
    private LogRepository $logRepository;

    private CurrentUser $currentUser;

    private ?LegacyRedisCache $legacyRedisCache;

    public function __construct(LogRepository $logRepository, CurrentUser $currentUser, ?LegacyRedisCache $legacyRedisCache)
    {
        $this->logRepository = $logRepository;
        $this->currentUser = $currentUser;
        $this->legacyRedisCache = $legacyRedisCache;
    }

    public function legacy(Request $request): View|RedirectResponse|Response
    {
        if (($abort = $this->logAccessGate()) !== null) {
            return $abort;
        }

        $currentUser = (array) ($this->currentUser->get() ?? []);
        $userId = (int) ($this->currentUser->id());

        $action = (string) ($request->input('action', 'dailylog'));
        $allowed = ['dailylog', 'chronicle', 'news', 'poll'];
        if (! in_array($action, $allowed, true)) {
            return $this->legacyAbortResponse(__('legacy/log.std_error'), __('legacy/log.std_invalid_action'));
        }

        return match ($action) {
            'dailylog' => $this->dailyLog($request),
            'chronicle' => $this->chronicle($request, $userId),
            'news' => $this->newsLog($request),
            'poll' => $this->pollLog($request),
        };
    }

    public function legacyPost(Request $request): View|RedirectResponse|Response
    {
        if (($abort = $this->logAccessGate()) !== null) {
            return $abort;
        }

        $currentUser = (array) ($this->currentUser->get() ?? []);
        $userId = (int) ($this->currentUser->id());

        $action = (string) ($request->input('action', 'dailylog'));
        $allowed = ['dailylog', 'chronicle', 'news', 'poll'];
        if (! in_array($action, $allowed, true)) {
            return $this->legacyAbortResponse(__('legacy/log.std_error'), __('legacy/log.std_invalid_action'));
        }

        // View-mode POSTs are idempotent — replay them as GET on the
        // canonical page; mutation bodies keep their 308s below.
        $qs = static function (array $params): string {
            $query = http_build_query(array_filter($params, static fn ($v) => $v !== null && $v !== ''), '', '&');

            return $query !== '' ? '?'.$query : '';
        };
        $inputs = fn (array $extra = []): string => $qs([
            'query' => $request->input('query'),
            'search' => $request->input('search'),
            'pollid' => $request->input('pollid'),
            'returnto' => $request->input('returnto'),
        ] + $extra);

        return match ($action) {
            'dailylog' => redirect('/web/log'.$inputs(['action' => 'dailylog'])),
            'news' => redirect('/web/log'.$inputs(['action' => 'news'])),
            'chronicle' => $this->chroniclePost($request, $userId),
            'poll' => $this->pollLogPost($request),
        };
    }

    private function dailyLog(Request $request): View|RedirectResponse
    {
        $q = htmlspecialchars(trim((string) ($request->input('query') ?? '')));
        $search = (string) ($request->input('search') ?? '');
        $canConfidential = Permission::can(PermissionEnum::CONFIDENTIAL_LOG);

        $filters = ['search' => $search, 'query' => $q];
        $count = $this->logRepository->countSiteLog($filters);

        $perpage = 50;
        $base = '?action=dailylog&'.($search !== '' && $canConfidential ? 'search='.rawurlencode($search).'&' : '').($q !== '' ? 'query='.rawurlencode($q).'&' : '');
        [$pagertop, $pagerbottom, , $offset] = Pagination::pager($perpage, $count, $base);

        $logRows = $this->logRepository->getSiteLog($filters, (int) $offset, $perpage);

        $userIds = array_filter(array_unique(array_column($logRows, 'uid')));
        UserDisplay::preload(array_map('intval', $userIds));
        $userDisplayMap = [];
        foreach ($userIds as $uid) {
            $userDisplayMap[(int) $uid] = (int) $uid > 0 ? UserDisplay::username((int) $uid) : 'System';
        }

        foreach ($logRows as &$row) {
            $txt = (string) ($row['txt'] ?? '');
            $row['colorClass'] = match (true) {
                str_starts_with($txt, 'STAFF ') => 'nx-color-darkred',
                str_contains($txt, 'settings updated by') => 'nx-color-darkred',
                str_contains($txt, 'was edited by') => 'nx-color-blue',
                str_contains($txt, 'was added to the Request section') => 'nx-color-purple',
                str_contains($txt, 'was deleted by') => 'nx-color-red',
                str_contains($txt, 'was uploaded by') => 'nx-color-green',
                default => '',
            };
            $row['dateHtml'] = SafeHtml::fromTrustedHtml((string) (Time::format((string) ($row['added'] ?? ''), true, false) ?? ''));
            $uid = (int) ($row['uid'] ?? 0);
            $row['usernameHtml'] = SafeHtml::fromTrustedHtml($uid > 0 ? (string) ($userDisplayMap[$uid] ?? UserDisplay::username($uid)) : 'System');
        }
        unset($row);

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
            'title' => __('legacy/log.head_site_log'),
        ]);
    }

    private function chronicle(Request $request, int $userId): View|RedirectResponse|Response
    {
        $q = htmlspecialchars(trim((string) ($request->input('query') ?? '')));
        $canManage = Permission::can(PermissionEnum::CHR_MANAGE);

        $do = (string) ($request->input('do') ?? '');

        // 'edit' is read-only (shows the edit form) and safe via GET.
        // 'add', 'update', 'del' are state-changing and require POST.
        if ($do === 'edit') {
            if (! $canManage) {
                return $this->legacyAbortResponse(__('legacy/log.std_error'), __('legacy/log.std_permission_denied'));
            }
            $id = (int) $request->input('id', 0);
            $editItem = $id > 0 ? $this->logRepository->getChronicleById($id) : null;
            if ($editItem === null) {
                return redirect('/web/log?action=chronicle');
            }

            return $this->chronicleList($request, $q, $canManage, $editItem);
        }

        return $this->chronicleList($request, $q, $canManage, null);
    }

    private function chroniclePost(Request $request, int $userId): RedirectResponse|Response
    {
        $q = htmlspecialchars(trim((string) ($request->input('query') ?? '')));
        $canManage = Permission::can(PermissionEnum::CHR_MANAGE);

        $do = (string) ($request->input('do') ?? '');

        if ($do === 'edit') {
            return redirect('/web/log?action=chronicle&do=edit&id='.(int) $request->input('id', 0));
        }

        if ($do !== '') {
            if (! $canManage) {
                return $this->legacyAbortResponse(__('legacy/log.std_error'), __('legacy/log.std_permission_denied'));
            }

            // Legacy forms can carry params in the URL (?do=del&id=N) —
            // forward the query string so the target endpoint sees them.
            $qs = $request->getQueryString();
            $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

            if ($do === 'add') {
                return redirect()->to('/web/log/chronicle/add'.$suffix, 308);
            }

            if ($do === 'update') {
                return redirect()->to('/web/log/chronicle/update'.$suffix, 308);
            }

            if ($do === 'del') {
                return redirect()->to('/web/log/chronicle/delete'.$suffix, 308);
            }
        }

        return redirect('/web/log?action=chronicle'.($q !== '' ? '&query='.rawurlencode($q) : ''));
    }

    public function chronicleAddPost(ChronicleAddRequest $request): RedirectResponse|Response
    {
        if (($abort = $this->logManageGate(PermissionEnum::CHR_MANAGE)) !== null) {
            return $abort;
        }

        $txt = (string) ($request->input('txt') ?? '');
        if ($txt !== '') {
            $currentUser = (array) ($this->currentUser->get() ?? []);
            $this->logRepository->addChronicle((int) ($this->currentUser->id()), $txt);
        }

        return redirect('/web/log?action=chronicle');
    }

    public function chronicleUpdatePost(ChronicleUpdateRequest $request): RedirectResponse|Response
    {
        if (($abort = $this->logManageGate(PermissionEnum::CHR_MANAGE)) !== null) {
            return $abort;
        }

        $id = (int) $request->input('id', 0);
        $txt = (string) ($request->input('txt') ?? '');
        if ($id <= 0) {
            return redirect('/web/log?action=chronicle');
        }
        if ($txt !== '') {
            $this->logRepository->updateChronicle($id, $txt);
        }

        return redirect('/web/log?action=chronicle');
    }

    public function chronicleDeletePost(ChronicleDeleteRequest $request): RedirectResponse|Response
    {
        if (($abort = $this->logManageGate(PermissionEnum::CHR_MANAGE)) !== null) {
            return $abort;
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return redirect('/web/log?action=chronicle');
        }
        $this->logRepository->deleteChronicle($id);

        return redirect('/web/log?action=chronicle');
    }

    public function pollDeletePost(PollDeleteRequest $request): RedirectResponse|Response
    {
        if (($abort = $this->logManageGate(PermissionEnum::POLL_MANAGE)) !== null) {
            return $abort;
        }

        $pollid = (int) $request->input('pollid', 0);
        $returnto = htmlspecialchars((string) ($request->input('returnto') ?? ''));
        if ($pollid <= 0) {
            return $this->legacyAbortResponse(__('legacy/log.std_error'), ('Invalid poll ID.'));
        }
        if ((int) $request->input('sure', 0) !== 1) {
            return $this->legacyAbortResponse(__('legacy/log.std_error'), __('legacy/log.std_permission_denied'));
        }
        $this->logRepository->deletePoll($pollid);

        if ($this->legacyRedisCache !== null) {
            $this->legacyRedisCache->delete_value('current_poll_content');
            $this->legacyRedisCache->delete_value('current_poll_result', true);
        }

        if ($returnto === 'main') {
            return redirect('/');
        }

        return redirect('/web/log?action=poll&deleted=1');
    }

    private function logAccessGate(): ?Response
    {
        if (Permission::can(PermissionEnum::LOG)) {
            return null;
        }

        $logClass = (int) SiteConfig::current()->authority->permission('log', 0);

        return $this->legacyAbortResponse(
            __('legacy/log.std_sorry'),
            (__('legacy/log.std_permission_denied_only')).UserClass::name($logClass, false, true, true).__('legacy/log.std_or_above_can_view').view('components.permission-faq-note', ['siteName' => Setting::getSiteName()])->render(),
            false
        );
    }

    private function logManageGate(PermissionEnum $permission): ?Response
    {
        if (($abort = $this->logAccessGate()) !== null) {
            return $abort;
        }

        if (! Permission::can($permission)) {
            return $this->legacyAbortResponse(__('legacy/log.std_error'), __('legacy/log.std_permission_denied'));
        }

        return null;
    }

    /**
     * @param  array<int|string, mixed>|null  $editItem
     */
    private function chronicleList(Request $request, string $q, bool $canManage, ?array $editItem): View|RedirectResponse
    {
        $count = $this->logRepository->countChronicle($q);
        $perpage = 50;
        $base = '?action=chronicle&'.($q !== '' ? 'query='.rawurlencode($q).'&' : '');
        [$pagertop, $pagerbottom, , $offset] = Pagination::pager($perpage, $count, $base);

        $chronicleRows = $this->logRepository->getChronicle($q, (int) $offset, $perpage);

        foreach ($chronicleRows as &$row) {
            $row['dateHtml'] = SafeHtml::fromTrustedHtml((string) (Time::format((string) ($row['added'] ?? ''), true, false) ?? ''));
            $row['bodyHtml'] = Format::formatComment((string) ($row['txt'] ?? ''), true, false, true);
        }
        unset($row);

        return $this->legacyPage($request, 'log', true, [
            'mode' => 'chronicle',
            'q' => $q,
            'chronicleRows' => $chronicleRows,
            'editItem' => $editItem,
            'count' => $count,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'canManage' => $canManage,
            'title' => __('legacy/log.head_chronicle'),
        ]);
    }

    private function newsLog(Request $request): View|RedirectResponse
    {
        $q = htmlspecialchars(trim((string) ($request->input('query') ?? '')));
        $search = (string) ($request->input('search') ?? '');

        $filters = ['search' => $search, 'query' => $q];
        $count = $this->logRepository->countNews($filters);

        $perpage = 20;
        $base = '?action=news&'.($search !== '' ? 'search='.rawurlencode($search).'&' : '').($q !== '' ? 'query='.rawurlencode($q).'&' : '');
        [$pagertop, $pagerbottom, , $offset] = Pagination::pager($perpage, $count, $base);

        $newsRows = $this->logRepository->getNews($filters, (int) $offset, $perpage);

        foreach ($newsRows as &$row) {
            $row['dateHtml'] = SafeHtml::fromTrustedHtml((string) (Time::format((string) ($row['added'] ?? ''), true, false) ?? ''));
            $row['bodyHtml'] = Format::formatComment((string) ($row['body'] ?? ''), false, false, true);
        }
        unset($row);

        return $this->legacyPage($request, 'log', true, [
            'mode' => 'news',
            'q' => $q,
            'search' => $search,
            'newsRows' => $newsRows,
            'count' => $count,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'title' => __('legacy/log.head_news'),
        ]);
    }

    private function pollLog(Request $request): View|RedirectResponse|Response
    {
        $do = (string) ($request->input('do') ?? '');
        $pollid = (int) $request->input('pollid', 0);
        $returnto = htmlspecialchars((string) ($request->input('returnto') ?? ''));

        if ($do === 'delete') {
            if (! Permission::can(PermissionEnum::POLL_MANAGE)) {
                return $this->legacyAbortResponse(__('legacy/log.std_error'), __('legacy/log.std_permission_denied'));
            }
            if ($pollid <= 0) {
                return $this->legacyAbortResponse(__('legacy/log.std_error'), ('Invalid poll ID.'));
            }
            $sureLinkText = (string) __('legacy/log.std_here');
            $sureSuffix = (string) __('legacy/log.std_if_sure');
            $confirm = view('log._delete_poll_confirm', [
                'pollid' => $pollid,
                'returnto' => $returnto,
                'token' => csrf_token(),
                'sureLinkText' => $sureLinkText,
                'sureSuffix' => $sureSuffix,
            ])->render();

            return $this->legacyAbortResponse(__('legacy/log.std_delete_poll'), $confirm, false);
        }

        $pollcount = $this->logRepository->getPollCount();
        if ($pollcount === 0) {
            return $this->legacyAbortResponse(__('legacy/log.std_sorry'), __('legacy/log.std_no_polls'));
        }

        $polls = $this->logRepository->getPollsExceptFirst();
        $pollData = [];
        foreach ($polls as $poll) {
            $options = [];
            for ($i = 0; $i < 20; $i++) {
                $optionText = (string) ($poll["option{$i}"] ?? '');
                if ($optionText !== '') {
                    $options[$i] = $optionText;
                }
            }

            $voteCounts = $this->logRepository->getPollVoteCounts((int) ($poll['id'] ?? 0));
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
                'added' => SafeHtml::fromTrustedHtml((string) Time::format($poll['added'] ?? '', true, false)),
                'totalVotes' => number_format($totalVotes),
                'options' => $computedOptions,
            ];
        }

        return $this->legacyPage($request, 'log', true, [
            'mode' => 'poll',
            'pollData' => $pollData,
            'canPollManage' => Permission::can(PermissionEnum::POLL_MANAGE),
            'title' => __('legacy/log.head_previous_polls'),
        ]);
    }

    private function pollLogPost(Request $request): RedirectResponse|Response
    {
        $do = (string) ($request->input('do') ?? '');

        if ($do === 'delete') {
            if (! Permission::can(PermissionEnum::POLL_MANAGE)) {
                return $this->legacyAbortResponse(__('legacy/log.std_error'), __('legacy/log.std_permission_denied'));
            }

            // The confirm form carries pollid/returnto in the URL — forward
            // the query string so the target endpoint still sees them.
            $qs = $request->getQueryString();
            $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

            return redirect()->to('/web/log/poll/delete'.$suffix, 308);
        }

        // View-mode POST — replay as GET on the canonical page.
        $params = array_filter([
            'action' => 'poll',
            'do' => $do !== '' ? $do : null,
            'pollid' => $request->input('pollid'),
            'returnto' => $request->input('returnto'),
        ], static fn ($v) => $v !== null && $v !== '');

        return redirect('/web/log?'.http_build_query($params, '', '&'));
    }
}
