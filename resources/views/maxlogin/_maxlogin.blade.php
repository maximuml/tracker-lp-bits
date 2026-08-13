<?php
function searchformMaxlogin() {
?>
<form method=post name=search action=maxlogin.php?>
<input type=hidden name=action value=searchip>
<p class=success align=center>Search IP <input type=text name=ip size=25> <input type=submit name=submit value='Search IP' class=btn></p>
</form>
<?php
}

if ($action === 'showlist') {
    \App\Support\Html::stdhead('Max. Login Attemps - Show List');
    print('<h1>Failed Login Attempts</h1>');
    print($msg);
    print('<table border=1 cellspacing=0 cellpadding=5 width=100%>\n');

    if (empty($rows)) {
        print('<tr><td colspan=2><b>Nothing found</b></td></tr>\n');
    } else {
        print('<tr><td class=colhead><a href=?order=id>ID</a></td><td class=colhead align=left><a href=?order=ip>Ip Address</a></td><td class=colhead align=left><a href=?order=added>Action Time</a></td>' .
            '<td class=colhead align=left><a href=?order=attempts>Attempts</a></td><td class=colhead align=left><a href=?order=type>Attempt Type</a></td><td class=colhead align=left><a href=?order=status>Status</a></td></tr>\n');

        foreach ($rows as $arr) {
            $userLink = $arr['userId'] ? \App\Support\UserDisplay::username($arr['userId']) : '';
            $typeLabel = $arr['type'] === 'recover' ? 'Recover Password Attempt!' : 'Login Attempt!';
            $statusLabel = $arr['banned'] === 'yes'
                ? '<font color=red><b>banned</b></font> <a href=maxlogin.php?action=unban&id=' . $arr['id'] . '><font color=green>[<b>unban</b>]</font></a>'
                : '<font color=green><b>not banned</b></font> <a href=maxlogin.php?action=ban&id=' . $arr['id'] . '><font color=red>[<b>ban</b>]</font></a>';
            print('<tr><td align=>' . $arr['id'] . '</td><td align=left>' . $arr['ip'] . ' ' . $userLink . '</td><td align=left>' . $arr['added'] . '</td><td align=left>' . $arr['attempts'] . '</td><td align=left>' . $typeLabel . '</td><td align=left>' . $statusLabel . '  <a OnClick="return confirm(\'Are you wish to delete this attempt?\');" href=maxlogin.php?action=delete&id=' . $arr['id'] . '>[<b>delete</b></a>] <a href=maxlogin.php?action=edit&id=' . $arr['id'] . '><font color=blue>[<b>edit</b></a>]</font></td></tr>\n');
        }
    }
    print('</table>');
    if ($countrows > $perpage) {
        echo $pagerbottom;
    }
    searchformMaxlogin();
    \App\Support\Html::stdfoot();
} elseif ($action === 'edit') {
    \App\Support\Html::stdhead('Max. Login Attemps - EDIT (' . htmlspecialchars((string) $editRow['id']) . ')');
    print('<table border=1 cellspacing=0 cellpadding=5 width=100%>\n');
    print('<tr><td><p>IP Address: <b>' . htmlspecialchars($editRow['ip']) . '</b></p>');
    print('<p>Action Time: <b>' . htmlspecialchars($editRow['added']) . '</b></p></tr></td>');
    print('<form method=\'post\' action=\'maxlogin.php\'>');
    print('<input type=\'hidden\' name=\'action\' value=\'save\'>');
    print('<input type=\'hidden\' name=\'id\' value=\'' . $editRow['id'] . '\'>');
    print('<input type=\'hidden\' name=\'ip\' value=\'' . $editRow['ip'] . '\'>');
    if ($returnto) {
        print('<input type=\'hidden\' name=\'returnto\' value=\'' . $returnto . '\'>');
    }
    print('<tr><td>Attempts <input type=\'text\' size=\'33\' name=\'attempts\' value=\'' . $editRow['attempts'] . '\'>');
    print('<tr><td>Attempt Type <select name=\'type\'><option value=\'login\' ' . ($editRow['type'] === 'login' ? 'selected' : '') . '>Login Attempt</option><option value=\'recover\' ' . ($editRow['type'] === 'recover' ? 'selected' : '') . '>Recover Password Attempts</option></select></tr></td>');
    print('<tr><td>Current Status <select name=\'banned\'><option value=\'yes\' ' . ($editRow['banned'] === 'yes' ? 'selected' : '') . '>Banned!</option><option value=\'no\' ' . ($editRow['banned'] === 'no' ? 'selected' : '') . '>Not Banned!</option></select></tr></td>');
    print('<tr><td><input type=\'submit\' name=\'submit\' value=\'Save\' class=btn></tr></td>');
    print('</table>');
    \App\Support\Html::stdfoot();
} elseif ($action === 'searchip') {
    \App\Support\Html::stdhead('Max. Login Attemps - Search');
    print('<h2>Failed Login Attempts</h2>');
    print('<table border=1 cellspacing=0 cellpadding=5 width=100%>\n');
    if (empty($rows)) {
        print('<tr><td colspan=2><b>Sorry, nothing found!</b></td></tr>\n');
    } else {
        print('<tr><td class=colhead><a href=?order=id>ID</a></td><td class=colhead align=left><a href=?order=ip>Ip Address</a></td><td class=colhead align=left><a href=?order=added>Action Time</a></td>' .
            '<td class=colhead align=left><a href=?order=attempts>Attempts</a></td><td class=colhead align=left><a href=?order=type>Attempt Type</a></td><td class=colhead align=left><a href=?order=status>Status</a></td></tr>\n');

        foreach ($rows as $arr) {
            $userLink = $arr['userId'] ? \App\Support\UserDisplay::username($arr['userId']) : '';
            $typeLabel = $arr['type'] === 'recover' ? 'Recover Password Attempt!' : 'Login Attempt!';
            $statusLabel = $arr['banned'] === 'yes'
                ? '<font color=red><b>banned</b></font> <a href=maxlogin.php?action=unban&id=' . $arr['id'] . '><font color=green>[<b>unban</b>]</font></a>'
                : '<font color=green><b>not banned</b></font> <a href=maxlogin.php?action=ban&id=' . $arr['id'] . '><font color=red>[<b>ban</b>]</font></a>';
            print('<tr><td align=>' . $arr['id'] . '</td><td align=left>' . $arr['ip'] . ' ' . $userLink . '</td><td align=left>' . $arr['added'] . '</td><td align=left>' . $arr['attempts'] . '</td><td align=left>' . $typeLabel . '</td><td align=left>' . $statusLabel . '  <a OnClick="return confirm(\'Are you wish to delete this attempt?\');" href=maxlogin.php?action=delete&id=' . $arr['id'] . '>[<b>delete</b></a>] <a href=maxlogin.php?action=edit&id=' . $arr['id'] . '><font color=blue>[<b>edit</b></a>]</font></td></tr>\n');
        }
    }
    print('</table>\n');
    searchformMaxlogin();
    \App\Support\Html::stdfoot();
}
?>
