<?php
$lang_testip = (array) ($lang_testip ?? \App\Support\SupportContext::getGlobal('lang_testip', []));

print('<h1>' . ($lang_testip['head_test_ip'] ?? 'Test IP address') . '</h1>');

if ($hasResult) {
    print('<table border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>' . $message . '</td></tr></table>');
    if ($banstable) {
        print('<p>' . $banstable . '</p>');
    }
}

print('<form method=post action=testip.php>');
print('<table border=1 cellspacing=0 cellpadding=5>');
print('<tr><td class=rowhead>' . ($lang_testip['text_ip_address'] ?? 'IP address') . '</td><td><input type=text name=ip value="'.htmlspecialchars($ip).'"></td></tr>');
print('<tr><td colspan=2 align=center><input type=submit class=btn value=\'OK\'></td></tr>');
print('</form>');
print('</table>');
?>
