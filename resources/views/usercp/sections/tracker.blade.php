@php
/** @var array<string, mixed> $tracker */
/** @var array<string, mixed> $lang */
/** @var array<string, mixed> $curUser */
/** @var string $type */
$lang_usercp = $lang;
$CURUSER = $curUser;
$CONTENT_WIDTH = $contentWidth;
$showtooltipsetting = $tracker['showTooltipSetting'];
$showshoutbox_main = $tracker['showShoutbox'] ? 'yes' : 'no';
$emailnotify_smtp = $tracker['showEmailNotify'] ? 'yes' : 'no';
$smtptype = (string) (\App\Support\SupportContext::getGlobal('smtptype', ''));
$notifs = (string) ($CURUSER['notifs'] ?? '');
@endphp
@include('usercp.sections._menu', ['selected' => 'tracker'])

@php
$formId = 'form'.\App\Support\Strings::randomCode(6);
@endphp
<form method=post action=usercp.php id="{{ $formId }}"><input type=hidden name=action value=tracker><input type=hidden name=type value=save>
<table border=0 cellspacing=0 cellpadding=5 width={{ $CONTENT_WIDTH }}>
@if ($type === 'saved')
<tr><td colspan=2 class="heading" valign="top" align="center"><font color=red>{{ $lang_usercp['text_saved'] ?? 'Saved' }}</font></td></tr>
@endif
@php
if ($emailnotify_smtp === 'yes' && $smtptype !== 'none'):
    \App\Support\Html::trSmall($lang_usercp['row_email_notification'] ?? 'Email notification', '<input type=checkbox name=pmnotif'.(str_contains($notifs, '[pm]') ? ' checked' : '').' value=yes> '.htmlspecialchars($lang_usercp['checkbox_notification_received_pm'] ?? '')."<br />\n<input type=checkbox name=emailnotif".(str_contains($notifs, '[email]') ? ' checked' : '').' value="yes" /> '.htmlspecialchars($lang_usercp['checkbox_notification_default_categories'] ?? ''), 1);
endif;

$categories = $tracker['categories'].$tracker['delimiter']."<table><caption><font class='big'>".htmlspecialchars($lang_usercp['text_additional_selection'] ?? '')."</font></caption><tr><td class=bottom><b>".htmlspecialchars($lang_usercp['text_show_dead_active'] ?? '').'</b><br /><select name="incldead"><option value="0" '.(str_contains($notifs, '[incldead=0]') ? ' selected' : '').'>'.htmlspecialchars($lang_usercp['select_including_dead'] ?? '').'</option><option value="1" '.(str_contains($notifs, '[incldead=1]') || ! str_contains($notifs, 'incldead') ? ' selected' : '').'>'.htmlspecialchars($lang_usercp['select_active'] ?? '').'</option><option value="2" '.(str_contains($notifs, '[incldead=2]') ? ' selected' : '').'>'.htmlspecialchars($lang_usercp['select_dead'] ?? '').'</option></select></td><td class=bottom align=left><b>'.htmlspecialchars($lang_usercp['text_show_special_torrents'] ?? '').'</b><br /><select name="spstate"><option value="0" '.($tracker['specialState'] === 0 ? ' selected' : '').'>'.htmlspecialchars($lang_usercp['select_all'] ?? '').'</option>'.$tracker['promotionSelection'].'</select></td><td class=bottom><b>'.htmlspecialchars($lang_usercp['text_show_bookmarked'] ?? '').'</b><br /><select name="inclbookmarked"><option value="0" '.(str_contains($notifs, '[inclbookmarked=0]') ? ' selected' : '').'>'.htmlspecialchars($lang_usercp['select_all'] ?? '').'</option><option value="1" '.(str_contains($notifs, '[inclbookmarked=1]') ? ' selected' : '').' >'.htmlspecialchars($lang_usercp['select_bookmarked'] ?? '').'</option><option value="2" '.(str_contains($notifs, '[inclbookmarked=2]') ? ' selected' : '').'>'.htmlspecialchars($lang_usercp['select_bookmarked_exclude'] ?? '').'</option></select></td></tr></table>';
\App\Support\Html::trSmall($lang_usercp['row_browse_default_categories'] ?? 'Browse categories', $categories, 1);

\App\Support\Html::trSmall($lang_usercp['row_stylesheet'] ?? 'Stylesheet', "<select name=stylesheet>\n".$tracker['stylesheetOptions']."\n</select>&nbsp;&nbsp;<font class=small>".htmlspecialchars($lang_usercp['text_stylesheet_note'] ?? '').'<a href="aboutnexus.php#stylesheet" ><b>'.htmlspecialchars($lang_usercp['text_stylesheet_link'] ?? '').'</b></a></font>.', 1);

\App\Support\Html::trSmall($lang_usercp['row_font_size'] ?? 'Font size', '<select name=fontsize><option value=small '.(($CURUSER['fontsize'] ?? '') === 'small' ? ' selected' : '').'>'.htmlspecialchars($lang_usercp['select_small'] ?? '').'</option><option value=medium '.(($CURUSER['fontsize'] ?? '') === 'medium' ? ' selected' : '').'>'.htmlspecialchars($lang_usercp['select_medium'] ?? '').'</option><option value=large '.(($CURUSER['fontsize'] ?? '') === 'large' ? ' selected' : '').'>'.htmlspecialchars($lang_usercp['select_large'] ?? '').'</option></select>', 1);

\App\Support\Html::trSmall($lang_usercp['row_site_language'] ?? 'Site language', "<select name=\"sitelanguage\">\n".$tracker['langOptions']."\n</select>&nbsp;&nbsp;<font class=small>".htmlspecialchars($lang_usercp['text_translation_note'] ?? '').'<a href="aboutnexus.php#translation"><b>'.htmlspecialchars($lang_usercp['text_translation_link'] ?? '').'</b></a></font>.', 1);

\App\Support\Html::trSmall($lang_usercp['row_pm_boxes'] ?? 'PM boxes', htmlspecialchars($lang_usercp['text_show'] ?? '').'<input type=text name=pmnum size=5 value='.(int) ($CURUSER['pmnum'] ?? 0).' >'.htmlspecialchars($lang_usercp['text_pms_per_page'] ?? ''), 1);

if ($showshoutbox_main === 'yes'):
    \App\Support\Html::trSmall($lang_usercp['row_shoutbox'] ?? 'Shoutbox', htmlspecialchars($lang_usercp['text_show_last'] ?? '').'<input type=text name=sbnum size=5 value='.(int) ($CURUSER['sbnum'] ?? 0).' >'.htmlspecialchars($lang_usercp['text_messages_at_shoutbox'] ?? '').'<br />'.htmlspecialchars($lang_usercp['text_refresh_shoutbox_every'] ?? '').'<input type=text name=sbrefresh size=5 value='.(int) ($CURUSER['sbrefresh'] ?? 0).' >'.htmlspecialchars($lang_usercp['text_seconds'] ?? ''), 1);
endif;

\App\Support\Html::trSmall($lang_usercp['row_torrent_detail'] ?? 'Torrent detail', '<input type=checkbox name=showdescription'.(($CURUSER['showdescription'] ?? '') === 'yes' ? ' checked' : '').' value=yes>'.htmlspecialchars($lang_usercp['text_show_description'] ?? ''), 1);
\App\Support\Html::trSmall($lang_usercp['row_discuss'] ?? 'Comments', '<input type=checkbox name=showcomment'.(($CURUSER['showcomment'] ?? '') === 'yes' ? ' checked' : '').' value=yes>'.htmlspecialchars($lang_usercp['text_show_comments'] ?? ''), 1);
\App\Support\Html::trSmall($lang_usercp['row_time_type'] ?? 'Time type', '<input type=radio name=timetype '.(($CURUSER['timetype'] ?? '') === 'timeadded' ? ' checked' : '').' value=timeadded>'.htmlspecialchars($lang_usercp['text_time_added'] ?? '').'&nbsp;&nbsp;<input type=radio name=timetype '.(($CURUSER['timetype'] ?? '') === 'timealive' ? ' checked' : '').' value=timealive>'.htmlspecialchars($lang_usercp['text_time_elapsed'] ?? '').'<br />', 1);

$browseCell = htmlspecialchars($lang_usercp['text_browse_setting_warning'] ?? '').'
<br /><b>'.htmlspecialchars($lang_usercp['row_torrent_page'] ?? '').': </b><br />'.htmlspecialchars($lang_usercp['text_show'] ?? '').'<input type=text size=5 name=torrentsperpage value='.(int) ($CURUSER['torrentsperpage'] ?? 0).'> '.htmlspecialchars($lang_usercp['text_torrents_per_page'] ?? '').htmlspecialchars($lang_usercp['text_zero_equals_default'] ?? '').'<br />'.
($showtooltipsetting ? '<b>'.htmlspecialchars($lang_usercp['text_tooltip_type'] ?? '').'</b>: <br /><input type=radio name=tooltip '.(($CURUSER['tooltip'] ?? '') === 'off' ? ' checked' : '').' value=off>'.htmlspecialchars($lang_usercp['text_off'] ?? '').'<br />' : '').
'<b>'.htmlspecialchars($lang_usercp['text_append_words_to_torrents'] ?? '').': </b><br /><input type=checkbox name=appendsticky '.(($CURUSER['appendsticky'] ?? '') === 'yes' ? ' checked' : '').' value=yes>'.htmlspecialchars($lang_usercp['text_append_sticky'] ?? '').'<br /><input type=checkbox name=appendnew '.(($CURUSER['appendnew'] ?? '') === 'yes' ? ' checked' : '').' value=yes>'.htmlspecialchars($lang_usercp['text_append_new'] ?? '').'<br />'.htmlspecialchars($lang_usercp['text_torrents_on_promotion'] ?? '').'<input type=radio name=appendpromotion '.(($CURUSER['appendpromotion'] ?? '') === 'highlight' ? ' checked' : '')." value='highlight'>".htmlspecialchars($lang_usercp['text_highlight'] ?? '').'<input type=radio name=appendpromotion '.(($CURUSER['appendpromotion'] ?? '') === 'word' ? ' checked' : '')." value='word'>".htmlspecialchars($lang_usercp['text_append_words'] ?? '').'<input type=radio name=appendpromotion '.(($CURUSER['appendpromotion'] ?? '') === 'icon' ? ' checked' : '')." value='icon'>".htmlspecialchars($lang_usercp['text_append_icon'] ?? '').'<input type=radio name=appendpromotion '.(($CURUSER['appendpromotion'] ?? '') === 'off' ? ' checked' : '')." value='off'>".htmlspecialchars($lang_usercp['text_no_mark'] ?? '').'<br /><input type=checkbox name=appendpicked '.(($CURUSER['appendpicked'] ?? '') === 'yes' ? ' checked' : '').' value=yes>'.htmlspecialchars($lang_usercp['text_append_picked'] ?? '').'<br />
<b>'.htmlspecialchars($lang_usercp['text_show_action_icons'] ?? '').': </b><br />'.'<input type=checkbox name=dlicon '.(($CURUSER['dlicon'] ?? '') === 'yes' ? ' checked' : '').' value=yes>'.htmlspecialchars($lang_usercp['text_show_download_icon'] ?? '').' <img class="download" src="pic/trans.gif"  alt="Download" /><br /><input type=checkbox name=bmicon '.(($CURUSER['bmicon'] ?? '') === 'yes' ? ' checked' : '').' value=yes>'.htmlspecialchars($lang_usercp['text_show_bookmark_icon'] ?? '').' <img class="bookmark" src="pic/trans.gif" alt="Bookmark" /><br />
<b>'.htmlspecialchars($lang_usercp['text_comments_reviews'] ?? '').': </b><br /><input type=checkbox name=showcomnum '.(($CURUSER['showcomnum'] ?? '') === 'yes' ? ' checked' : '').' value=yes>'.htmlspecialchars($lang_usercp['text_show_comment_number'] ?? '').($showtooltipsetting ? '<select name="showlastcom" style="width: 70px;"><option value="yes" '.(($CURUSER['showlastcom'] ?? 'no') !== 'no' ? ' selected' : '').'>'.htmlspecialchars($lang_usercp['select_with'] ?? '').'</option><option value="no" '.(($CURUSER['showlastcom'] ?? '') === 'no' ? ' selected' : '').'>'.htmlspecialchars($lang_usercp['select_without'] ?? '').'</option></select>'.htmlspecialchars($lang_usercp['text_last_comment_on_tooltip'] ?? '') : ''), 1);
\App\Support\Html::trSmall($lang_usercp['row_browse_page'] ?? 'Browse page', $browseCell, 1);
@endphp
<tr><td class="rowhead" valign="top" align="right">{{ $lang_usercp['row_save_settings'] ?? 'Save' }}</td><td class="rowfollow" valign="top" align=left><input type=submit value="{{ $lang_usercp['submit_save_settings'] ?? 'Save' }}"></td></tr>
</table></form>
