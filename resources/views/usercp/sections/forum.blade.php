@php
/** @var array<string, mixed> $forum */
/** @var array<string, mixed> $lang */
/** @var array<string, mixed> $curUser */
/** @var string $type */
$lang_usercp = $lang;
$CURUSER = $curUser;
$CONTENT_WIDTH = $contentWidth;
$showtooltipsetting = $forum['showTooltipSetting'];
@endphp
@include('usercp.sections._menu', ['selected' => 'forum'])

@php
$formId = 'form'.\App\Support\Strings::randomCode(6);
@endphp
<form method=post action=usercp.php id="{{ $formId }}"><input type=hidden name=action value=forum><input type=hidden name=type value=save>
<table border=0 cellspacing=0 cellpadding=5 width={{ $CONTENT_WIDTH }}>
@if ($type === 'saved')
<tr><td colspan=2 class="heading" valign="top" align="center"><font color=red>{{ $lang_usercp['text_saved'] ?? 'Saved' }}</font></td></tr>
@endif
@php
\App\Support\Html::trSmall($lang_usercp['row_topics_per_page'] ?? 'Topics per page', "<input type=text size=10 name=topicsperpage value=".(int) ($CURUSER['topicsperpage'] ?? 0).">".htmlspecialchars($lang_usercp['text_zero_equals_default'] ?? ''), 1);
\App\Support\Html::trSmall($lang_usercp['row_posts_per_page'] ?? 'Posts per page', "<input type=text size=10 name=postsperpage value=".(int) ($CURUSER['postsperpage'] ?? 0)."> ".htmlspecialchars($lang_usercp['text_zero_equals_default'] ?? ''), 1);
\App\Support\Html::trSmall($lang_usercp['row_view_avatars'] ?? 'View avatars', '<input type=checkbox name=avatars'.(($CURUSER['avatars'] ?? '') === 'yes' ? ' checked' : '').'>'.htmlspecialchars($lang_usercp['checkbox_low_bandwidth_note'] ?? ''), 1);
\App\Support\Html::trSmall($lang_usercp['row_view_signatures'] ?? 'View signatures', '<input type=checkbox name=signatures'.(($CURUSER['signatures'] ?? '') === 'yes' ? ' checked' : '').'>'.htmlspecialchars($lang_usercp['checkbox_low_bandwidth_note'] ?? ''), 1);
if ($showtooltipsetting):
    \App\Support\Html::tr($lang_usercp['row_tooltip_last_post'] ?? 'Tooltip last post', '<input type=checkbox name=ttlastpost'.(($CURUSER['showlastpost'] ?? '') === 'yes' ? ' checked' : '').'>'.htmlspecialchars($lang_usercp['checkbox_last_post_note'] ?? ''), 1);
endif;
\App\Support\Html::trSmall($lang_usercp['row_click_on_topic'] ?? 'Click on topic', '<input type=radio name=clicktopic'.(($CURUSER['clicktopic'] ?? '') === 'firstpage' ? ' checked' : '').' value="firstpage">'.htmlspecialchars($lang_usercp['text_go_to_first_page'] ?? '').'<input type=radio name=clicktopic'.(($CURUSER['clicktopic'] ?? '') === 'lastpage' ? ' checked' : '').' value="lastpage">'.htmlspecialchars($lang_usercp['text_go_to_last_page'] ?? ''), 1);
\App\Support\Html::trSmall($lang_usercp['row_forum_signature'] ?? 'Forum signature', '<textarea name=signature style="width:700px" rows=10>'.htmlspecialchars((string) ($CURUSER['signature'] ?? '')).'</textarea><br />'.htmlspecialchars($lang_usercp['text_signature_note'] ?? ''), 1);
@endphp
<tr><td class="rowhead" valign="top" align="right">{{ $lang_usercp['row_save_settings'] ?? 'Save' }}</td><td class="rowfollow" valign="top" align=left><input type=submit value="{{ $lang_usercp['submit_save_settings'] ?? 'Save' }}"></td></tr>
</table></form>
