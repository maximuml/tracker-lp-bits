<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\UserSearchRepositoryInterface;
use App\Models\User;
use App\Repositories\UserListingRepository;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\RequestValues;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\Ratio;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\Validators;
use App\ViewModels\Usersearch\UserRatioCell;
use App\ViewModels\Usersearch\UsersearchResultsViewModel;
use App\ViewModels\Usersearch\UsersearchRow;
use App\ViewModels\UsersearchPageViewModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Prepares data for the administrative user search page, replacing the
 * legacy usersearch_content.php partial with a typed Blade-rendered view.
 *
 * The page renders a search form (always shown) and, when query parameters
 * are present, a paginated results table with user stats.
 */
final class UsersearchPageService
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly UserSearchRepositoryInterface $userSearchRepository,
        private readonly UserListingRepository $userListingRepository,
    ) {}

    /**
     * Build the data for the user search page.
     */
    public function build(Request $request): UsersearchPageViewModel
    {
        $curUser = (array) ($this->currentUser->get() ?? []);
        $requestUri = (string) RequestValues::serverValue('REQUEST_URI');
        $hasModcomment = Schema::hasColumn('users', 'modcomment');

        if (UserDisplay::currentClass() < UC_MODERATOR) {
            LegacyResponse::abort('Error', 'Permission denied.');
        }

        $highlight = 'nx-hl';
        $showHelp = ! empty(request()->query('h'));

        // Build form field values and highlight state
        $form = $this->buildFormFields($highlight);

        // Build results (only when query params present and not help view)
        $results = null;
        $resultsError = null;
        $hasResults = false;
        if (count(request()->query()) > 0 && empty(request()->query('h'))) {
            $hasResults = true;
            try {
                $results = $this->buildResults($curUser, $hasModcomment, $requestUri);
            } catch (\InvalidArgumentException $e) {
                $resultsError = SafeHtml::fromTrustedHtml(view('partials.std-message', [
                    'heading' => 'Error',
                    'text' => $e->getMessage(),
                    'htmlstrip' => false,
                    'body' => null,
                ])->render());
            }
        }

        return new UsersearchPageViewModel(
            requestUri: $requestUri,
            showHelp: $showHelp,
            form: $form,
            hasResults: $hasResults,
            results: $results,
            resultsError: $resultsError,
            pagemenu: '',
            browsemenu: '',
        );
    }

    /**
     * Build the form field values and highlight flags.
     *
     * @return array<string, mixed>
     */
    private function buildFormFields(string $highlight): array
    {
        $q = static fn (string $key): ?string => is_string($val = request()->query($key)) ? $val : null;

        $class = $q('c');
        if (! Validators::isId($class)) {
            $class = '';
        }

        // Build class select options
        $classOptions = [['value' => 1, 'label' => '(any)', 'selected' => false]];
        $classKeys = array_map('intval', array_keys(User::$classes));
        $maxClass = $classKeys !== [] ? max($classKeys) : 0;
        for ($i = 2; $i - 2 <= $maxClass; $i++) {
            if (! ($c = UserClass::name($i - 2, false, true, true))->isEmpty()) {
                $classOptions[] = ['value' => $i, 'label' => $c, 'selected' => (bool) $class && $class == $i];
            } else {
                break;
            }
        }

        return [
            'n' => htmlspecialchars((string) ($q('n') ?? '')),
            'n_hl' => $q('n') ? $highlight : '',
            'r' => htmlspecialchars((string) ($q('r') ?? '')),
            'r2' => htmlspecialchars((string) ($q('r2') ?? '')),
            'rt' => (string) ($q('rt') ?? ''),
            'rt_options' => $this->selectOptions(['equal', 'above', 'below', 'between'], (string) ($q('rt') ?? '')),
            'st' => (string) ($q('st') ?? ''),
            'st_options' => $this->selectOptions(['(any)', 'confirmed', 'pending'], (string) ($q('st') ?? '')),
            'em' => htmlspecialchars((string) ($q('em') ?? '')),
            'em_hl' => $q('em') ? $highlight : '',
            'ip' => htmlspecialchars((string) ($q('ip') ?? '')),
            'ip_hl' => $q('ip') ? $highlight : '',
            'as' => (string) ($q('as') ?? ''),
            'as_options' => $this->selectOptions(['(any)', 'enabled', 'disabled'], (string) ($q('as') ?? '')),
            'co' => htmlspecialchars((string) ($q('co') ?? '')),
            'co_hl' => $q('co') ? $highlight : '',
            'ma' => htmlspecialchars((string) ($q('ma') ?? '')),
            'ma_hl' => $q('ma') ? $highlight : '',
            'c' => $class,
            'c_hl' => ($q('c') && $q('c') != 1) ? $highlight : '',
            'c_options' => $classOptions,
            'd' => htmlspecialchars((string) ($q('d') ?? '')),
            'd2' => htmlspecialchars((string) ($q('d2') ?? '')),
            'dt' => (string) ($q('dt') ?? ''),
            'dt_options' => $this->selectOptions(['on', 'before', 'after', 'between'], (string) ($q('dt') ?? '')),
            'd_hl' => $q('d') ? $highlight : '',
            'ul' => htmlspecialchars((string) ($q('ul') ?? '')),
            'ul2' => htmlspecialchars((string) ($q('ul2') ?? '')),
            'ult' => (string) ($q('ult') ?? ''),
            'ult_options' => $this->selectOptions(['equal', 'above', 'below', 'between'], (string) ($q('ult') ?? '')),
            'ul_hl' => $q('ul') ? $highlight : '',
            'do' => (string) ($q('do') ?? ''),
            'do_options' => $this->selectOptions(['(any)', 'Yes', 'No'], (string) ($q('do') ?? '')),
            'do_hl' => $q('do') ? $highlight : '',
            'ls' => htmlspecialchars((string) ($q('ls') ?? '')),
            'ls2' => htmlspecialchars((string) ($q('ls2') ?? '')),
            'lst' => (string) ($q('lst') ?? ''),
            'lst_options' => $this->selectOptions(['on', 'before', 'after', 'between'], (string) ($q('lst') ?? '')),
            'ls_hl' => $q('ls') ? $highlight : '',
            'dl' => htmlspecialchars((string) ($q('dl') ?? '')),
            'dl2' => htmlspecialchars((string) ($q('dl2') ?? '')),
            'dlt' => (string) ($q('dlt') ?? ''),
            'dlt_options' => $this->selectOptions(['equal', 'above', 'below', 'between'], (string) ($q('dlt') ?? '')),
            'dl_hl' => $q('dl') ? $highlight : '',
            'w' => (string) ($q('w') ?? ''),
            'w_options' => $this->selectOptions(['(any)', 'Yes', 'No'], (string) ($q('w') ?? '')),
            'w_hl' => $q('w') ? $highlight : '',
            'ac' => (bool) $q('ac'),
            'ac_hl' => $q('ac') ? $highlight : '',
            'dip' => (bool) $q('dip'),
            'dip_hl' => $q('dip') ? $highlight : '',
        ];
    }

    /**
     * Build option data for a select, marking the selected value.
     *
     * @param  array<int, string>  $options
     * @return list<array{value: int, label: string, selected: bool}>
     */
    private function selectOptions(array $options, string $selected): array
    {
        $out = [];
        for ($i = 0; $i < count($options); $i++) {
            $out[] = ['value' => $i, 'label' => $options[$i], 'selected' => $selected === (string) $i];
        }

        return $out;
    }

    /**
     * Build the results table view model.
     *
     * @param  array<string, mixed>  $curUser
     */
    private function buildResults(array $curUser, bool $hasModcomment, string $requestUri): UsersearchResultsViewModel
    {
        $searchResult = $this->userSearchRepository->administrativeSearch((array) request()->query(), $hasModcomment, 30);
        $count = (int) $searchResult['count'];
        $q = (string) $searchResult['q'];
        $perpage = 30;
        [$pagertop, $pagerbottom, , $offset, $rpp] = Pagination::pager($perpage, $count, $requestUri.'?'.$q);
        $res = $searchResult['rows'];

        $userIds = array_map(fn ($row) => (int) ($row['id'] ?? 0), $res);
        $ips = array_map(fn ($row) => (string) ($row['ip'] ?? ''), $res);
        UserDisplay::preload($userIds);
        $extraStats = $this->userListingRepository->getSearchExtraStats($userIds, $ips, (int) ($curUser['class'] ?? 0));
        $peerTotals = $extraStats['peers'];
        $postCounts = $extraStats['posts'];
        $commentCounts = $extraStats['comments'];
        $bannedIps = $extraStats['bannedIps'];

        if (count($res) == 0) {
            return new UsersearchResultsViewModel([], false, SafeHtml::fromTrustedHtml(''), SafeHtml::fromTrustedHtml(''), SafeHtml::fromTrustedHtml(view('partials.std-message', [
                'heading' => 'Warning',
                'text' => 'No user was found.',
                'htmlstrip' => false,
                'body' => null,
            ])->render()));
        }

        $rows = [];
        foreach ($res as $user) {
            $user = (array) $user;
            $added = (string) ($user['added'] ?? '');
            if ($added === '0000-00-00 00:00:00' || $added === '') {
                $added = '---';
            }
            $lastAccess = (string) ($user['last_access'] ?? '');
            if ($lastAccess === '0000-00-00 00:00:00' || $lastAccess === '') {
                $lastAccess = '---';
            }

            $ip = (string) ($user['ip'] ?? '');
            $ipBanned = $ip !== ''
                && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                && isset($bannedIps[$ip]);

            $peerTotal = $peerTotals[(int) $user['id']] ?? ['pul' => 0, 'pdl' => 0];
            $pul = (float) ($peerTotal['pul'] ?? 0);
            $pdl = (float) ($peerTotal['pdl'] ?? 0);

            $rows[] = new UsersearchRow(
                id: (int) $user['id'],
                username: UserDisplay::username((int) $user['id']),
                ratio: $this->ratioCell((float) $user['uploaded'], (float) $user['downloaded']),
                ip: $ip !== '' ? $ip : '---',
                ipBanned: $ipBanned,
                email: (string) $user['email'],
                added: $added,
                lastAccess: $lastAccess,
                status: (string) $user['status'],
                enabled: (string) $user['enabled'],
                peerRatio: $this->ratioCell($pul, $pdl),
                peerUploaded: Format::size($pul),
                peerDownloaded: Format::size($pdl),
                postCount: (int) ($postCounts[(int) $user['id']] ?? 0),
                commentCount: (int) ($commentCounts[(int) $user['id']] ?? 0),
            );
        }

        return new UsersearchResultsViewModel(
            rows: $rows,
            showPager: $count > $perpage,
            pagerTop: SafeHtml::fromTrustedHtml((string) $pagertop),
            pagerBottom: SafeHtml::fromTrustedHtml((string) $pagerbottom),
            emptyMessage: null,
        );
    }

    /**
     * Format a ratio cell with optional color.
     */
    private function ratioCell(float $up, float $down, bool $color = true): UserRatioCell
    {
        if ($down > 0) {
            $r = number_format($up / $down, 2);

            return new UserRatioCell($r, $color ? Ratio::colorClass($r) : null);
        }

        return new UserRatioCell($up > 0 ? 'Inf.' : '---');
    }
}
