<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\UserListingRepository;
use App\Repositories\UserSearchRepository;
use App\Support\Format;
use App\Support\Frame;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\Ratio;
use App\Support\SupportContext;
use App\Support\UserClass;
use App\Support\UserDisplay;
use App\Support\Validators;
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
    /**
     * Build the data for the user search page.
     *
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $curUser = (array) (SupportContext::getUser() ?? []);
        $requestUri = (string) SupportContext::getServerValue('REQUEST_URI');
        $hasModcomment = Schema::hasColumn('users', 'modcomment');

        if (UserDisplay::currentClass() < UC_MODERATOR) {
            LegacyResponse::abort('Error', 'Permission denied.');
        }

        $highlight = ' bgcolor=#BBAF9B';
        $showHelp = ! empty(request()->query('h'));

        // Build form field values and highlight state
        $form = $this->buildFormFields($highlight);

        // Build results (only when query params present and not help view)
        $resultsHtml = '';
        $resultsError = '';
        $hasResults = false;
        if (count(request()->query()) > 0 && empty(request()->query('h'))) {
            $hasResults = true;
            try {
                $resultsHtml = $this->buildResults($curUser, $hasModcomment, $requestUri);
            } catch (\InvalidArgumentException $e) {
                $resultsError = Frame::stdMessage('Error', $e->getMessage(), false);
            }
        }

        return [
            'requestUri' => $requestUri,
            'showHelp' => $showHelp,
            'form' => $form,
            'hasResults' => $hasResults,
            'resultsHtml' => $resultsHtml,
            'resultsError' => $resultsError,
            'pagemenu' => '',
            'browsemenu' => '',
        ];
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
        $classOptions = "<option value='1'>(any)</option>\n";
        $classKeys = array_map('intval', array_keys(User::$classes));
        $maxClass = $classKeys !== [] ? max($classKeys) : 0;
        for ($i = 2; $i - 2 <= $maxClass; $i++) {
            if ($c = UserClass::name($i - 2, false, true, true)) {
                $classOptions .= '<option value='.$i.($class && $class == $i ? ' selected' : '').">$c</option>\n";
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
     * Build <option> tags for a select, marking the selected value.
     *
     * @param  array<int, string>  $options
     */
    private function selectOptions(array $options, string $selected): string
    {
        $out = '';
        for ($i = 0; $i < count($options); $i++) {
            $out .= "<option value=$i ".($selected == "$i" ? 'selected' : '').'>'.$options[$i]."</option>\n";
        }

        return $out;
    }

    /**
     * Build the results table HTML.
     *
     * @param  array<string, mixed>  $curUser
     */
    private function buildResults(array $curUser, bool $hasModcomment, string $requestUri): string
    {
        $searchResult = UserSearchRepository::administrativeSearch((array) request()->query(), $hasModcomment, 30);
        $count = (int) $searchResult['count'];
        $q = (string) $searchResult['q'];
        $perpage = 30;
        [$pagertop, $pagerbottom, , $offset, $rpp] = Pagination::pager($perpage, $count, $requestUri.'?'.$q);
        $res = $searchResult['rows'];

        $userIds = array_map(fn ($row) => (int) ($row['id'] ?? 0), $res);
        $ips = array_map(fn ($row) => (string) ($row['ip'] ?? ''), $res);
        $extraStats = UserListingRepository::getSearchExtraStats($userIds, $ips, (int) ($curUser['class'] ?? 0));
        $peerTotals = $extraStats['peers'];
        $postCounts = $extraStats['posts'];
        $commentCounts = $extraStats['comments'];
        $bannedIps = $extraStats['bannedIps'];

        if (count($res) == 0) {
            return Frame::stdMessage('Warning', 'No user was found.', false);
        }

        ob_start();
        if ($count > $perpage) {
            echo $pagertop;
        }
        echo "<table border=1 cellspacing=0 cellpadding=5>\n";
        echo '<tr><td class=colhead align=left>Name</td>
    		<td class=colhead align=left>Ratio</td>
        <td class=colhead align=left>IP</td>
        <td class=colhead align=left>Email</td>'.
            '<td class=colhead align=left>Joined:</td>'.
            '<td class=colhead align=left>Last seen:</td>'.
            '<td class=colhead align=left>Status</td>'.
            '<td class=colhead align=left>Enabled</td>'.
            '<td class=colhead>pR</td>'.
            '<td class=colhead>pUL</td>'.
            '<td class=colhead>pDL</td>'.
            '<td class=colhead>History</td></tr>';
        foreach ($res as $user) {
            $user = (array) $user;
            if ($user['added'] == '0000-00-00 00:00:00' || $user['added'] == null) {
                $user['added'] = '---';
            }
            if ($user['last_access'] == '0000-00-00 00:00:00' || $user['last_access'] == null) {
                $user['last_access'] = '---';
            }

            if ($user['ip']) {
                $ipstr = $user['ip'];
                if (filter_var($user['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && isset($bannedIps[$user['ip']])) {
                    $ipstr = "<a href='testip.php?ip=".$user['ip']."'><font color='#FF0000'><b>".$user['ip'].'</b></font></a>';
                }
            } else {
                $ipstr = '---';
            }

            $peerTotal = $peerTotals[(int) $user['id']] ?? ['pul' => 0, 'pdl' => 0];
            $pul = (float) ($peerTotal['pul'] ?? 0);
            $pdl = (float) ($peerTotal['pdl'] ?? 0);

            $n_posts = (int) ($postCounts[(int) $user['id']] ?? 0);
            $n_comments = (int) ($commentCounts[(int) $user['id']] ?? 0);

            echo '<tr><td>'.
                  UserDisplay::username((int) $user['id']).'</td>'.
              '<td>'.$this->ratios((float) $user['uploaded'], (float) $user['downloaded']).'</td>
          <td>'.$ipstr.'</td><td>'.(string) $user['email'].'</td>
          <td><div align=center>'.(string) $user['added'].'</div></td>
          <td><div align=center>'.(string) $user['last_access'].'</div></td>
          <td><div align=center>'.(string) $user['status'].'</div></td>
          <td><div align=center>'.(string) $user['enabled'].'</div></td>
          <td><div align=center>'.$this->ratios($pul, $pdl).'</div></td>'.
              '<td><div align=right>'.Format::size($pul).'</div></td>
          <td><div align=right>'.Format::size($pdl).'</div></td>
          <td><div align=center>'.($n_posts ? '<a href=userhistory.php?action=viewposts&id='.(int) $user['id'].">$n_posts</a>" : $n_posts).
              '|'.($n_comments ? '<a href=userhistory.php?action=viewcomments&id='.(int) $user['id'].">$n_comments</a>" : $n_comments).
              "</div></td></tr>\n";
        }
        echo '</table>';
        if ($count > $perpage) {
            echo "$pagerbottom";
        }

        return (string) ob_get_clean();
    }

    /**
     * Format a ratio string with optional color.
     */
    private function ratios(float $up, float $down, bool $color = true): string
    {
        if ($down > 0) {
            $r = number_format($up / $down, 2);
            if ($color) {
                $r = '<font color='.Ratio::color($r).">$r</font>";
            }
        } elseif ($up > 0) {
            $r = 'Inf.';
        } else {
            $r = '---';
        }

        return $r;
    }
}
