<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($lang_shoutbox)) $lang_shoutbox = (array) (\App\Support\SupportContext::getGlobal('lang_shoutbox') ?? []);

$perPage = (int) ($perPage ?? 50);
$page = (int) ($page ?? 1);
$filters = (array) ($filters ?? []);
$rows = (array) ($rows ?? []);
$total = (int) ($total ?? 0);
$currentUserId = (int) ($currentUserId ?? 0);
$isStaff = (bool) ($isStaff ?? false);
$csrfToken = (string) ($csrfToken ?? '');
$reactionData = (array) ($reactionData ?? ['counts' => [], 'mine' => [], 'users' => []]);
$userDisplayMap = (array) ($userDisplayMap ?? []);

$reactionCounts = $reactionData['counts'] ?? [];
$reactionMine = $reactionData['mine'] ?? [];
$reactionUsers = $reactionData['users'] ?? [];

$formAction = 'shoutbox_history.php';

echo '<script>var SHOUT_CSRF = \'' . htmlspecialchars($csrfToken) . '\';</script>';

echo '<h2>' . ($lang_shoutbox['text_history_title'] ?? 'Shoutbox history') . '</h2>';
echo '<form action="' . htmlspecialchars($formAction) . '" method="get">';
echo '<table border="0" cellspacing="0" cellpadding="5">';
echo '<tr><td>' . ($lang_shoutbox['text_username'] ?? 'Username') . '</td><td><input type="text" name="user" value="' . htmlspecialchars($filters['user'] ?? '') . '" /></td>';
echo '<td>' . ($lang_shoutbox['text_from'] ?? 'From') . '</td><td><input type="date" name="from" value="' . htmlspecialchars($filters['from'] ?? '') . '" /></td>';
echo '<td>' . ($lang_shoutbox['text_to'] ?? 'To') . '</td><td><input type="date" name="to" value="' . htmlspecialchars($filters['to'] ?? '') . '" /></td></tr>';
echo '<tr><td>' . ($lang_shoutbox['text_search'] ?? 'Search') . '</td><td><input type="text" name="search" value="' . htmlspecialchars($filters['search'] ?? '') . '" /></td>';
echo '<td colspan="4"><input type="submit" class="btn" value="' . htmlspecialchars($lang_shoutbox['text_filter'] ?? 'Filter') . '" /></td></tr>';
echo '</table></form>';

echo '<table border="0" cellspacing="0" cellpadding="2" width="100%">';

foreach ($rows as $arr) {
    $time = \App\Support\Shoutbox::formatTime((int) $arr['date'], true);
    $uid = (int) $arr['userid'];
    $username = $uid > 0 ? ($userDisplayMap[$uid] ?? '') : ($lang_shoutbox['text_guest'] ?? '<b>Guest</b>');
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
