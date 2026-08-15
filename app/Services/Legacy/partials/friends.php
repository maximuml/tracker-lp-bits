<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_friends)) $lang_friends = (array) (\App\Support\SupportContext::getGlobal('lang_friends') ?? []);

$userid = (int) ($userid ?? 0);
$friendsList = (array) ($friendsList ?? []);
$blockRows = (array) ($blockRows ?? []);
$userDisplayMap = (array) ($userDisplayMap ?? []);
$titleUsername = (string) ($titleUsername ?? '');
$canViewUserList = (bool) ($canViewUserList ?? false);

print("<p><table class=main border=0 cellspacing=0 cellpadding=0>" .
    "<tr><td class=embedded><h1 style='margin:0px'> " . ($lang_friends['text_personallist'] ?? 'Personal list for ') . " " . $titleUsername . "</h1></td></tr></table></p>\n");

print("<table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>");
print("<br />");
print("<h2 align=left><a name=\"friends\">" . ($lang_friends['text_friendlist'] ?? 'Friend list') . "</a></h2>\n");
print("<table width=737 border=1 cellspacing=0 cellpadding=5><tr class=tablea><td>");

$i = 0;
if (empty($friendsList)) {
    $friends = $lang_friends['text_friends_empty'] ?? 'No friends.';
    print($friends);
} else {
    foreach ($friendsList as $friend) {
        $friendId = (int) ($friend['id'] ?? 0);
        $title = (string) ($friend['title'] ?? '');
        $lastAccess = (string) ($friend['last_access'] ?? '');
        $avatar = ($CURUSER['avatars'] ?? '') === 'yes' ? htmlspecialchars((string) ($friend['avatar'] ?? '')) : '';
        if (!$avatar) {
            $avatar = 'pic/default_avatar.png';
        }

        $usernameHtml = $userDisplayMap[$friendId] ?? \App\Support\UserDisplay::username($friendId);
        $body1 = $usernameHtml . " ($title)<br /><br />" . ($lang_friends['text_last_seen_on'] ?? 'Last seen on ') . \App\Support\Time::format($lastAccess, true, false);
        $body2 = "<a href=friends.php?id=$userid&action=delete&type=friend&targetid=$friendId>" . ($lang_friends['text_remove_from_friends'] ?? 'Remove from friends') . "</a>" .
            "<br /><br /><a href=sendmessage.php?receiver=$friendId>" . ($lang_friends['text_send_pm'] ?? 'Send PM') . "</a>";

        if ($i % 2 == 0) {
            print("<table width=100% style='padding: 0px'><tr><td class=bottom style='padding: 5px' width=50% align=center>");
        } else {
            print("<td class=bottom style='padding: 5px' width=50% align=center class=tablea>");
        }
        print("<table class=main width=100% height=75px class=tablea>");
        print("<tr valign=top class=tableb><td width=75 align=center style='padding: 0px'>" .
            ($avatar ? "<div style='width:75px;height:75px;overflow: hidden'><img width=75px src=\"$avatar\"></div>" : '') . "</td><td>\n");
        print("<table class=main>");
        print("<tr><td class=embedded style='padding: 5px' width=80%>$body1</td>\n");
        print("<td class=embedded style='padding: 5px' width=20%>$body2</td></tr>\n");
        print("</table>");
        print("</td></tr>");
        print("</td></tr></table>\n");
        if ($i % 2 == 1) {
            print("</td></tr></table>\n");
        } else {
            print("</td>\n");
        }
        $i++;
    }
}
if ($i % 2 == 1) {
    print("<td class=bottom width=50%>&nbsp;</td></tr></table>\n");
}

print("</td></tr></table><br />\n");

$blocks = '';
if (empty($blockRows)) {
    $blocks = $lang_friends['text_blocklist_empty'] ?? 'No blocked users.';
} else {
    $i = 0;
    $blocks = "<table width=100% cellspacing=0 cellpadding=0>";
    foreach ($blockRows as $block) {
        $blockId = (int) ($block['id'] ?? 0);
        if ($i % 6 == 0) {
            $blocks .= "<tr>";
        }
        $blocks .= "<td style='border: none; padding: 4px; spacing: 0px;'>[<font class=small><a href=friends.php?id=$userid&action=delete&type=block&targetid=$blockId>D</a></font>] " .
            ($userDisplayMap[$blockId] ?? \App\Support\UserDisplay::username($blockId)) . "</td>";
        if ($i % 6 == 5) {
            $blocks .= "</tr>";
        }
        $i++;
    }
    $blocks .= "</table>\n";
}

print("<br /><br />");
print("<table class=main width=737 border=0 cellspacing=0 cellpadding=5><tr><td class=embedded>");
print("<h2 align=left><a name=\"blocks\">" . ($lang_friends['text_blocked_users'] ?? 'Blocked users') . "</a></h2></td></tr>");
print("<tr class=tableb><td style='padding: 10px;'>");
print($blocks);
print("</td></tr></table>\n");

print("</td></tr></table>\n");
if ($canViewUserList) {
    print("<p><a href=users.php><b>" . ($lang_friends['text_find_user'] ?? 'Find user') . "</b></a></p>");
}
