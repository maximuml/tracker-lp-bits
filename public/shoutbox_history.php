<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path('shoutbox.php'));
loggedinorreturn(true);

\Nexus\Nexus::css('styles/shoutbox.css', 'header', true);
\Nexus\Nexus::js('js/shoutbox.js', 'footer', true);

stdhead($lang_shoutbox['text_history_title'] ?? 'Shoutbox history');
begin_main_frame();

$perPage = 50;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$filters = [
    'user' => trim((string) ($_GET['user'] ?? '')),
    'from' => trim((string) ($_GET['from'] ?? '')),
    'to' => trim((string) ($_GET['to'] ?? '')),
    'search' => trim((string) ($_GET['search'] ?? '')),
    'type' => trim((string) ($_GET['type'] ?? '')),
];

$currentUserId = (int) ($CURUSER['id'] ?? 0);
echo '<script>var SHOUT_CSRF = \'' . htmlspecialchars(\App\Support\Shoutbox::csrfToken($currentUserId)) . '\';</script>';
$isStaff = user_can('sbmanage');
$canViewHb = $isStaff || ($showhelpbox_main == 'yes' && ($CURUSER['hidehb'] ?? '') != 'yes');

// A regular user who cannot see helpbox messages in the main shoutbox should
// not be able to enumerate them through the history page either.
$effectiveType = $filters['type'];
if (! $canViewHb) {
    if ($effectiveType === 'hb') {
        $effectiveType = 'sb';
    } elseif ($effectiveType === '') {
        $effectiveType = 'sb';
    }
}

$query = \Nexus\Database\NexusDB::table('shoutbox')
    ->orderByDesc('date')
    ->offset($offset)
    ->limit($perPage);
$countQuery = \Nexus\Database\NexusDB::table('shoutbox');

if ($effectiveType === 'sb' || $effectiveType === 'hb') {
    $query->where('type', $effectiveType);
    $countQuery->where('type', $effectiveType);
}

if ($filters['user'] !== '') {
    $userId = \Nexus\Database\NexusDB::table('users')
        ->whereRaw('LOWER(username) = LOWER(?)', [$filters['user']])
        ->value('id');
    if ($userId) {
        $query->where('userid', (int) $userId);
        $countQuery->where('userid', (int) $userId);
    } else {
        $query->where('userid', -1);
        $countQuery->where('userid', -1);
    }
}

if ($filters['from'] !== '') {
    $fromTs = strtotime($filters['from']);
    if ($fromTs !== false) {
        $query->where('date', '>=', $fromTs);
        $countQuery->where('date', '>=', $fromTs);
    }
}
if ($filters['to'] !== '') {
    $toTs = strtotime($filters['to']);
    if ($toTs !== false) {
        $query->where('date', '<=', $toTs + 86399);
        $countQuery->where('date', '<=', $toTs + 86399);
    }
}

if ($filters['search'] !== '') {
    $like = '%' . $filters['search'] . '%';
    $query->where('text', 'like', $like);
    $countQuery->where('text', 'like', $like);
}

$rows = $query->get()->map(fn ($r) => (array) $r)->all();
$total = (int) $countQuery->count();

$formAction = 'shoutbox_history.php';
$typeOptions = [
    '' => $lang_shoutbox['text_all_types'] ?? 'All',
    'sb' => $lang_shoutbox['text_type_shoutbox'] ?? 'Shoutbox',
];
if ($canViewHb) {
    $typeOptions['hb'] = $lang_shoutbox['text_type_helpbox'] ?? 'Helpbox';
}
$selectedType = $canViewHb ? $filters['type'] : ($filters['type'] === 'hb' ? 'sb' : $filters['type']);

echo '<h2>' . ($lang_shoutbox['text_history_title'] ?? 'Shoutbox history') . '</h2>';
echo '<form action="' . htmlspecialchars($formAction) . '" method="get">';
echo '<table border="0" cellspacing="0" cellpadding="5">';
echo '<tr><td>' . ($lang_shoutbox['text_username'] ?? 'Username') . '</td><td><input type="text" name="user" value="' . htmlspecialchars($filters['user']) . '" /></td>';
echo '<td>' . ($lang_shoutbox['text_from'] ?? 'From') . '</td><td><input type="date" name="from" value="' . htmlspecialchars($filters['from']) . '" /></td>';
echo '<td>' . ($lang_shoutbox['text_to'] ?? 'To') . '</td><td><input type="date" name="to" value="' . htmlspecialchars($filters['to']) . '" /></td></tr>';
echo '<tr><td>' . ($lang_shoutbox['text_search'] ?? 'Search') . '</td><td><input type="text" name="search" value="' . htmlspecialchars($filters['search']) . '" /></td>';
echo '<td>' . ($lang_shoutbox['text_type'] ?? 'Type') . '</td><td><select name="type">';
foreach ($typeOptions as $value => $label) {
    echo '<option value="' . htmlspecialchars($value) . '"' . ($selectedType === $value ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
}
echo '</select></td>';
echo '<td colspan="2"><input type="submit" class="btn" value="' . htmlspecialchars($lang_shoutbox['text_filter'] ?? 'Filter') . '" /></td></tr>';
echo '</table></form>';

echo '<table border="0" cellspacing="0" cellpadding="2" width="100%">';

$shoutIds = array_map(fn ($r) => (int) $r['id'], $rows);
$reactionData = \App\Support\Shoutbox::prefetchReactions($shoutIds, $currentUserId);
$reactionCounts = $reactionData['counts'];
$reactionMine = $reactionData['mine'];
$reactionUsers = $reactionData['users'];

foreach ($rows as $arr) {
    $time = \App\Support\Shoutbox::formatTime((int) $arr['date'], true);
    $username = $arr['userid'] ? get_username((int) $arr['userid'], false, true, true, true, false, false, '', true) : ($lang_shoutbox['text_guest'] ?? '<b>Guest</b>');
    $shoutId = (int) $arr['id'];
    $actions = \App\Support\Shoutbox::renderActions($arr, $currentUserId, $isStaff);
    $reactions = \App\Support\Shoutbox::renderReactions(
        $shoutId,
        $currentUserId,
        $reactionCounts[$shoutId] ?? [],
        $reactionMine[$shoutId] ?? [],
        $reactionUsers[$shoutId] ?? []
    );
    $mentionsMe = false;
    $message = \App\Support\Shoutbox::formatMessage($arr['text'], $currentUserId, $mentionsMe);
    $editedNote = '';
    if (!empty($arr['edited_at']) && (int)$arr['edited_at'] > 0) {
        $editedNote = ' <span class="shout-edited-note">(' . htmlspecialchars((string) ($lang_shoutbox['text_edited'] ?? 'edited')) . ' ' . \App\Support\Shoutbox::formatTime((int)$arr['edited_at'], true) . ')</span>';
    }
    $messageHtml = '<span id="shout-msg-' . $shoutId . '" class="shout-msg" data-raw="' . htmlspecialchars((string) $arr['text'], ENT_QUOTES) . '">' . $message . '</span>' . $editedNote;

    echo '<tr><td class="shoutrow' . ($mentionsMe ? ' shoutrow-mentions-me' : '') . '">';
    echo '<span class="date">[' . $time . ']</span> ' . $actions . ' ' . $username . ' ' . $reactions;
    echo '<div>' . $messageHtml . '</div>';
    echo '</td></tr>';
}
echo '</table>';

$totalPages = (int) ceil($total / $perPage);
if ($totalPages > 1) {
    echo '<div class="pagination">';
    $base = $formAction . '?' . http_build_query(array_filter($filters, fn ($v) => $v !== '')) . '&page=';
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == $page) {
            echo ' <b>' . $i . '</b> ';
        } else {
            echo ' <a href="' . htmlspecialchars($base . $i) . '">' . $i . '</a> ';
        }
    }
    echo '</div>';
}

end_main_frame();
stdfoot();
