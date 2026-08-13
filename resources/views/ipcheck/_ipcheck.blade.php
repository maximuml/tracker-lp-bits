<?php
\App\Support\Html::stdhead($title);
print '<h1>' . $title . '</h1>';
\App\Support\Html::beginTable();
?>
<tr align=center>
    <td class=colhead width=90>User</td>
    <td class=colhead width=70>Email</td>
    <td class=colhead width=70>Registered</td>
    <td class=colhead width=75>Last access</td>
    <td class=colhead width=70>Downloaded</td>
    <td class=colhead width=70>Uploaded</td>
    <td class=colhead width=45>Ratio</td>
    <td class=colhead width=125>IP</td>
    <td class=colhead width=40>Peer</td>
</tr>
<?php $uc = 0; foreach ($rows as $arr): $uc++; $utc = ($uc % 2 == 0) ? '' : ' bgcolor="ECE9D8"'; ?>
<tr<?php echo $utc; ?>>
    <td align="left"><?php echo \App\Support\UserDisplay::username($arr['id']); ?></td>
    <td align="center"><?php echo htmlspecialchars((string) $arr['email']); ?></td>
    <td align="center"><?php echo htmlspecialchars((string) $arr['added_date']); ?></td>
    <td align="center"><?php echo htmlspecialchars((string) $arr['last_access_date']); ?></td>
    <td align="center"><?php echo htmlspecialchars((string) $arr['downloaded_str']); ?></td>
    <td align="center"><?php echo htmlspecialchars((string) $arr['uploaded_str']); ?></td>
    <td align="center"><?php echo $arr['ratio_html']; ?></td>
    <td align="center"><a href="http://www.whois.sc/<?php echo htmlspecialchars((string) $arr['ip']); ?>" target="_blank"><?php echo htmlspecialchars((string) $arr['ip']); ?></a></td>
    <td align="center"><?php echo $arr['peer_count'] ? 'ja' : 'nein'; ?></td>
</tr>
<?php endforeach; ?>
<?php
\App\Support\Html::endFrame();
\App\Support\Html::endTable();
\App\Support\Html::stdfoot();
?>
