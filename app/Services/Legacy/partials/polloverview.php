<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (!isset($lang_polloverview)) $lang_polloverview = (array) (\App\Support\SupportContext::getGlobal('lang_polloverview') ?? []);

$mode = (string) ($mode ?? 'list');
$poll = (array) ($poll ?? []);
$polls = (array) ($polls ?? []);
$answers = (array) ($answers ?? []);
$count = (int) ($count ?? 0);
$pagertop = (string) ($pagertop ?? '');
$pagerbottom = (string) ($pagerbottom ?? '');
$userDisplayMap = (array) ($userDisplayMap ?? []);

if ($mode === 'detail') {
    $pollid = (int) ($poll['id'] ?? 0);
    print("<h1 align=\"center\">" . ($lang_polloverview['text_polls_overview'] ?? 'Polls overview') . "</h1>\n");

    print("<table width=737 border=1 cellspacing=0 cellpadding=5><tr>\n" .
        "<td class=colhead align=center><nobr>" . ($lang_polloverview['col_id'] ?? 'ID') . "</nobr></td><td class=colhead><nobr>" . ($lang_polloverview['col_added'] ?? 'Added') . "</nobr></td><td class=colhead><nobr>" . ($lang_polloverview['col_question'] ?? 'Question') . "</nobr></td></tr>\n");

    $added = \App\Support\Time::format($poll['added'] ?? '');
    print("<tr><td align=center><a href=\"polloverview.php?id=" . $pollid . "\">" . $pollid . "</a></td><td>" . $added . "</td><td><a href=\"polloverview.php?id=" . $pollid . "\">" . ($poll['question'] ?? '') . "</a></td></tr>\n");
    print("</table>\n");

    print("<h1 align=\"center\">" . ($lang_polloverview['text_poll_question'] ?? 'Poll question') . "</h1><br />\n");
    print("<table width=737 border=1 cellspacing=0 cellpadding=5><tr><td class=colhead>" . ($lang_polloverview['col_option_no'] ?? 'Option #') . "</td><td class=colhead>" . ($lang_polloverview['col_options'] ?? 'Options') . "</td></tr>\n");
    for ($i = 0; $i < 20; $i++) {
        $option = (string) ($poll["option{$i}"] ?? '');
        if ($option !== '') {
            print("<tr><td>" . $i . "</td><td>" . $option . "</td></tr>\n");
        }
    }
    print("</table>\n");

    print("<h1 align=\"center\">" . ($lang_polloverview['text_polls_user_overview'] ?? 'Users voted') . "</h1>\n");

    if ($count == 0) {
        print("<p align=\"center\">" . ($lang_polloverview['text_no_users_voted'] ?? 'No users voted.') . "</p>");
    } else {
        print($pagertop);
        print("<table width=737 border=1 cellspacing=0 cellpadding=5>");
        print("<tr><td class=colhead align=center><nobr>" . ($lang_polloverview['col_username'] ?? 'Username') . "</nobr></td><td class=colhead align=center><nobr>" . ($lang_polloverview['col_selection'] ?? 'Selection') . "<nobr></td></tr>\n");
        foreach ($answers as $answerRow) {
            $useras = (array) $answerRow;
            $uid = (int) ($useras['userid'] ?? 0);
            $selection = (int) ($useras['selection'] ?? 0);
            $username = $userDisplayMap[$uid] ?? \App\Support\UserDisplay::username($uid);
            print("<tr><td>" . $username . "</td><td>" . ($poll["option{$selection}"] ?? '') . "</td></tr>\n");
        }
        print("</table>\n");
        print($pagerbottom);
    }
} else {
    if (empty($polls)) {
        \App\Support\LegacyResponse::abort($lang_polloverview['std_error'] ?? 'Error', $lang_polloverview['text_no_users_voted'] ?? 'No polls found.');
    }
    print("<h1 align=\"center\">" . ($lang_polloverview['text_polls_overview'] ?? 'Polls overview') . "</h1>\n");

    print("<table width=737 border=1 cellspacing=0 cellpadding=5><tr>\n" .
        "<td class=colhead align=center><nobr>" . ($lang_polloverview['col_id'] ?? 'ID') . "</nobr></td><td class=colhead>" . ($lang_polloverview['col_added'] ?? 'Added') . "</td><td class=colhead><nobr>" . ($lang_polloverview['col_question'] ?? 'Question') . "</nobr></td></tr>\n");
    foreach ($polls as $pollRow) {
        $poll = (array) $pollRow;
        $added = \App\Support\Time::format($poll['added'] ?? '');
        print("<tr><td align=center><a href=\"polloverview.php?id=" . $poll['id'] . "\">" . $poll['id'] . "</a></td><td>" . $added . "</td><td><a href=\"polloverview.php?id=" . $poll['id'] . "\">" . $poll['question'] . "</a></td></tr>\n");
    }
    print("</table>\n");
}
