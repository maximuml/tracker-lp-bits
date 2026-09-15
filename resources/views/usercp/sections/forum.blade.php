@include('usercp.sections._menu', ['selected' => 'forum'])

<form method=post action=usercp.php id="{{ $forum['formId'] ?? '' }}"><input type=hidden name=action value=forum><input type=hidden name=type value=save>
<div class="nx-fgrid nx-fgrid--flat">
@if ($type === 'saved')
<div class="nx-ffull nx-center"><font color=red><b>{{ $lang['text_saved'] ?? 'Saved' }}</b></font></div>
@endif
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($forum['rowsHtml'] ?? ''))
<div class="nx-fhead">{{ $lang['row_save_settings'] ?? 'Save' }}</div><div class="nx-fcell"><input type=submit value="{{ $lang['submit_save_settings'] ?? 'Save' }}"></div>
</div></form>
