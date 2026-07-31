<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path('shoutbox.php'));
loggedinorreturn(true);

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

$query = \Nexus\Database\NexusDB::table('shoutbox')
    ->orderByDesc('date')
    ->offset($offset)
    ->limit($perPage);
$countQuery = \Nexus\Database\NexusDB::table('shoutbox');

if ($filters['type'] === 'sb' || $filters['type'] === 'hb') {
    $query->where('type', $filters['type']);
    $countQuery->where('type', $filters['type']);
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
$currentUserId = (int) ($CURUSER['id'] ?? 0);
$isStaff = user_can('sbmanage');

$formAction = $_SERVER['PHP_SELF'];
$typeOptions = [
    '' => $lang_shoutbox['text_all_types'] ?? 'All',
    'sb' => $lang_shoutbox['text_type_shoutbox'] ?? 'Shoutbox',
    'hb' => $lang_shoutbox['text_type_helpbox'] ?? 'Helpbox',
];
$selectedType = $filters['type'];

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

echo '<link rel="stylesheet" href="styles/shoutbox.css" type="text/css">';

echo '<table border="0" cellspacing="0" cellpadding="2" width="100%">';
foreach ($rows as $arr) {
    $time = \App\Support\Shoutbox::formatTime((int) $arr['date'], true);
    $username = $arr['userid'] ? get_username((int) $arr['userid'], false, true, true, true, false, false, '', true) : ($lang_shoutbox['text_guest'] ?? '<b>Guest</b>');
    $actions = \App\Support\Shoutbox::renderActions($arr, $currentUserId, $isStaff);
    $reactions = \App\Support\Shoutbox::renderReactions((int) $arr['id'], $currentUserId);
    $mentionsMe = false;
    $message = \App\Support\Shoutbox::formatMessage($arr['text'], $currentUserId, $mentionsMe);
    echo '<tr><td class="shoutrow">';
    echo '<span class="date">[' . $time . ']</span> ' . $actions . ' ' . $username . ' ' . $reactions;
    echo '<div>' . $message . '</div>';
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
