@include('usercp.sections._menu', ['selected' => 'forum'])

<form method=post action=usercp.php id="{{ $forum['formId'] ?? '' }}"><input type=hidden name=action value=forum><input type=hidden name=type value=save>
<table border=0 cellspacing=0 cellpadding=5 width={{ $contentWidth }}>
@if ($type === 'saved')
<tr><td colspan=2 class="heading" valign="top" align="center"><font color=red>{{ $lang['text_saved'] ?? 'Saved' }}</font></td></tr>
@endif
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($forum['rowsHtml'] ?? ''))
<tr><td class="rowhead" valign="top" align="right">{{ $lang['row_save_settings'] ?? 'Save' }}</td><td class="rowfollow" valign="top" align=left><input type=submit value="{{ $lang['submit_save_settings'] ?? 'Save' }}"></td></tr>
</table></form>
