@include('usercp.sections._menu', ['selected' => 'security'])

<form method=post action=usercp.php id="security"><input type=hidden name=action value=security><input type=hidden name=type value={{ $security['isConfirm'] ? 'confirm' : 'save' }}>
<table border=0 cellspacing=0 cellpadding=5 width={{ $contentWidth }}>
@if ($security['isConfirm'])
@if (($security['confirmHidden']['resetpasskey'] ?? '') === '1')<input type="hidden" name="resetpasskey" value="1">@endif
@if (($security['confirmHidden']['resetauthkey'] ?? '') === '1')<input type="hidden" name="resetauthkey" value="1">@endif
<input type="hidden" name="email" value="{{ $security['confirmHidden']['email'] ?? '' }}">
<input type="hidden" name="chpassword" value="{{ $security['confirmHidden']['chpassword'] ?? '' }}">
<input type="hidden" name="privacy" value="{{ $security['confirmHidden']['privacy'] ?? '' }}">
<input type="hidden" name="two_step_secret" value="{{ $security['confirmHidden']['two_step_secret'] ?? '' }}">
<input type="hidden" name="two_step_code" value="{{ $security['confirmHidden']['two_step_code'] ?? '' }}">
<tr><td class="rowhead nowrap" valign="top" align="right" width=1%>{{ $lang['row_security_check'] ?? 'Check' }}</td><td valign="top" align="left" width="99%"><input type=password class=oldpassword style="width: 200px"><br /><font class=small>{{ $lang['text_security_check_note'] ?? '' }}</font></td></tr>
<input type=hidden name=username value="{{ (string) ($curUser['username'] ?? '') }}">
<input type=hidden name=response>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($security['confirmHtml'] ?? ''))
<tr><td class="rowhead" valign="top" align="right">{{ $lang['row_save_settings'] ?? 'Save' }}</td><td class="rowfollow" valign="top" align=left><input type=button value="{{ $lang['submit_save_settings'] ?? 'Save' }}"></td></tr>
</table></form>
@else
@if ($type === 'saved')
<tr><td colspan=2 class="heading" valign="top" align="center"><font color=red>{{ $security['savedMessage'] }}</font></td></tr>
@endif
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($security['rowsHtml'] ?? ''))
<input type="hidden" name="chpassword" />
<tr><td class="rowhead" valign="top" align="right">{{ $lang['row_save_settings'] ?? 'Save' }}</td><td class="rowfollow" valign="top" align=left><input type=button value="{{ $lang['submit_save_settings'] ?? 'Save' }}"></td></tr>
</table></form>
@endif
