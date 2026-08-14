<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (! isset($CURUSER)) {
    $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
}
if (! isset($lang_downloadnotice)) {
    $lang_downloadnotice = (array) (\App\Support\SupportContext::getGlobal('lang_downloadnotice') ?? []);
}

?>
<h2><?php echo $title ?? '' ?></h2>
<table width="100%"><tr>
<td colspan="2" class="text" align="left"><p><?php echo $note ?? '' ?></p></td></tr>
<tr>
<?php
if (! empty($showrationotice))
{
?>
<td class="text" align="left" valign="top" <?php echo $tdattr ?? '' ?>>
<h3><?php echo $lang_downloadnotice['text_this_is_private_tracker'] ?></h3>
<p><?php echo $lang_downloadnotice['text_private_tracker_note_one'] ?><i>(<?php echo $lang_downloadnotice['text_learn_more'] ?><a class="faqlink" href="<?php echo NEXUSWIKIURL ?>/Private Tracker" target="_blank"><?php echo $lang_downloadnotice['text_nexuswiki'] ?></a>)</i></p>
<p><?php echo $lang_downloadnotice['text_private_tracker_note_two'] ?><i>(<?php echo $lang_downloadnotice['text_see_ratio'] ?><a class="faqlink" href="faq.php#id23" target="_blank"><?php echo $lang_downloadnotice['text_faq'] ?></a>)</i></p>
<p><?php echo $lang_downloadnotice['text_private_tracker_note_three'] ?></p>
<img src="pic/ratio.png" alt="ratio" />
<p><?php echo $lang_downloadnotice['text_private_tracker_note_four'] ?></p>
</td>
<?php
}
if (! empty($showclientnotice))
{
?>
<td class="text" align="left" valign="top" <?php echo $tdattr ?? '' ?>>
<h3><?php echo $lang_downloadnotice['text_use_allowed_clients'] ?></h3>
<p><?php echo $lang_downloadnotice['text_allowed_clients_note_one'] ?></p>
<p><?php echo $lang_downloadnotice['text_allowed_clients_note_two'] ?><a class='faqlink' href='faq.php#id29' target='_blank'><?php echo $lang_downloadnotice['text_faq'] ?></a><?php echo $lang_downloadnotice['text_allowed_clients_note_three'] ?></p>
<table width="100%">
<tr>
<td class="embedded" style="text-align: center; padding: 5px;" width="50%">
<a href="https://www.qbittorrent.org/download" target="_blank" title="<?php echo $lang_downloadnotice['title_download'] ?>qBittorrent"><img src="pic/qbittorrent.png" alt="qBittorrent"  width="128" height="128" /></a>
</td>
<td class="embedded" style="text-align: center; padding: 5px;" width="50%">
<a href="https://transmissionbt.com/download/" target="_blank" title="<?php echo $lang_downloadnotice['title_download'] ?>Transmission"><img src="pic/transmission.png" alt="Transmission"  width="128" height="128" /></a>
</td>
</tr>
<tr>
<td class="embedded" style="text-align: center; padding: 5px;">
<div class="big"><a href="https://www.qbittorrent.org/download" target="_blank" title="<?php echo $lang_downloadnotice['title_download'] ?>qBittorrent"><b>qBittorrent</b></a></div>
<div><?php echo $lang_downloadnotice['text_for'] ?>Windows, Linux, Mac OS</div>
</td>
<td class="embedded" style="text-align: center; padding: 5px;">
<div class="big"><a href="https://transmissionbt.com/download/" target="_blank" title="<?php echo $lang_downloadnotice['title_download'] ?>Transmission"><b>Transmission</b></a></div>
<div><?php echo $lang_downloadnotice['text_for'] ?>Windows, Linux, Mac OS</div>
</td>
</tr>
</table>
</td>
<?php
}
?>
</tr>
<?php
if (! empty($torrentid))
{
?>
<tr>
<td class="text" colspan="2">
<form action="?" method="post"><p><?php echo $lang_downloadnotice['text_for_more_information_read'] ?><a class="faqlink" href="rules.php" target="_blank"><?php echo $lang_downloadnotice['text_rules'] ?></a><?php echo $lang_downloadnotice['text_and'] ?><a class="faqlink" href="faq.php" target="_blank"><?php echo $lang_downloadnotice['text_faq'] ?></a><br />
<input type="hidden" name="id" value="<?php echo $torrentid ?>" />
<input type="hidden" name="type" value="<?php echo htmlspecialchars((string) ($type ?? 'firsttime')) ?>" />
<input type="checkbox" name="hidenotice" id="hidenotice" value="1"<?php echo ! empty($forcecheck) ? " disabled=\"disabled\"" : " checked=\"checked\"" ?> /><label for="hidenotice"><?php echo $noticenexttime ?? '' ?></label>
<?php
if (! empty($forcecheck))
{
?>
<br /><input type="checkbox" name="letmedown" id="letmedown" value="<?php echo htmlspecialchars((string) ($type ?? 'firsttime')) ?>" onclick="if (this.checked) {document.getElementById('continuedownload').disabled = false;}else{document.getElementById('continuedownload').disabled = true;}" /><label for="letmedown"><span class="big"><?php echo $lang_downloadnotice['text_let_me_download'] ?></span></label>
<?php
}
?>
</p>
<div><input type="submit" name="submit" id="continuedownload" style="font-size: 20pt; height: 40px;" value="<?php echo $lang_downloadnotice['submit_download_the_torrent'] ?>"<?php echo ! empty($forcecheck) ? " disabled=\"disabled\"" : "" ?> /></div>
</form>
</td>
</tr>
<?php
}
?>
</table>
<?php
