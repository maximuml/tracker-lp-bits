<?php
$lang_ipsearch = (array) ($lang_ipsearch ?? \App\Support\SupportContext::getGlobal('lang_ipsearch', []));

\App\Support\Html::stdhead($lang_ipsearch['head_search_ip_history'] ?? 'Search IP History');
\App\Support\Frame::mainFrameOpen();

print('<h1 align="center">' . ($lang_ipsearch['text_search_ip_history'] ?? 'Search IP History') . '</h1>' . "\n");
print('<form method="get" action="">');
print('<table align=center border=1 cellspacing=0 width=115 cellpadding=5>' . "\n");
\App\Support\Html::tr(($lang_ipsearch['row_ip'] ?? 'IP') . '<font color=red>*</font>', '<input type="text" name="ip" size="40" value="' . htmlspecialchars($ip) . '" />', 1);
\App\Support\Html::tr('<nobr>' . ($lang_ipsearch['row_subnet_mask'] ?? 'Subnet Mask') . '</nobr>', '<input type="text" name="mask" size="40" value="' . htmlspecialchars($mask) . '" />', 1);
print('<tr><td align="right" colspan="2"><input type="submit" value="' . ($lang_ipsearch['submit_search'] ?? 'Search') . '"/></td></tr>');
print('</table></form>' . "\n");

if ($ip) {
    if ($count == 0) {
        print('<p align="center">' . ($lang_ipsearch['text_no_users_found'] ?? 'No users found.') . '</p>' . "\n");
    } else {
        print('<h1 align="center">' . $count . ($lang_ipsearch['text_users_used_the_ip'] ?? ' users used the IP ') . $ip . '</h1>');

        $contentWidth = defined('CONTENT_WIDTH') ? constant('CONTENT_WIDTH') : '900';
        print('<table width=' . $contentWidth . ' border=1 cellspacing=0 cellpadding=5 align=center>' . "\n");
        print('<tr><td class=colhead align=center><a class=colhead href="?ip=' . urlencode($ip) . '&mask=' . urlencode($mask) . '&order=username">' . ($lang_ipsearch['col_username'] ?? 'Username') . '</a></td>' .
            '<td class=colhead align=center><a class=colhead href="?ip=' . urlencode($ip) . '&mask=' . urlencode($mask) . '&order=last_ip">' . ($lang_ipsearch['col_last_ip'] ?? 'Last IP') . '</a></td>' .
            '<td class=colhead align=center><a class=colhead href="?ip=' . urlencode($ip) . '&mask=' . urlencode($mask) . '&order=last_access">' . ($lang_ipsearch['col_last_access'] ?? 'Last Access') . '</a></td>' .
            '<td class=colhead align=center>' . ($lang_ipsearch['col_ip_num'] ?? 'IP #') . '</td>' .
            '<td class=colhead align=center><a class=colhead href="?ip=' . urlencode($ip) . '&mask=' . urlencode($mask) . '">' . ($lang_ipsearch['col_last_access_on'] ?? 'Last Access On') . '</a></td>' .
            '<td class=colhead align=center><a class=colhead href="?ip=' . urlencode($ip) . '&mask=' . urlencode($mask) . '&order=added">' . ($lang_ipsearch['col_added'] ?? 'Added') . '</a></td>' .
            '<td class=colhead align=center>' . ($lang_ipsearch['col_invited_by'] ?? 'Invited By') . '</td></tr>');

        foreach ($rows as $row) {
            print('<tr><td align="center">' . $row['username_html'] . '</td>' .
                '<td align="center">' . htmlspecialchars($row['ipstr']) . '</td>' .
                '<td align="center">' . htmlspecialchars($row['lastaccess']) . '</td>' .
                '<td align="center"><a href="iphistory.php?id=' . $row['id'] . '">' . $row['iphistory'] . '</a></td>' .
                '<td align="center">' . $row['access'] . '</td>' .
                '<td align="center">' . $row['added'] . '</td>' .
                '<td align="center">' . $row['invited_by'] . '</td></tr>' . "\n");
        }
        print('</table>');
        print($pagerbottom);
    }
}

\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();
?>
