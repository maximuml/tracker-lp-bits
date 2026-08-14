<?php
$lang_iphistory = (array) (\App\Support\SupportContext::getGlobal('lang_iphistory') ?? []);

print('<h1 align="center">'.$lang_iphistory['text_historical_ip_by'] . \App\Support\UserDisplay::username($userid).'</h1>');

if ($countrows > $perpage)
echo $pagertop;

print('<table width=500 border=1 cellspacing=0 cellpadding=5 align=center>\n');
print('<tr>\n
<td class=colhead>'.$lang_iphistory['col_last_access'].'</td>\n
<td class=colhead>'.$lang_iphistory['col_ip'].'</td>\n
<td class=colhead>'.$lang_iphistory['col_hostname'].'</td>\n
</tr>\n');
foreach ($rows as $row) {
    print('<tr><td>'.$row['date'].'</td>\n');
    print('<td>'.$row['ipshow'].'</td>\n');
    print('<td>'.$row['addr'].'</td></tr>\n');
}

print('</table>');

echo $pagerbottom;


?>
