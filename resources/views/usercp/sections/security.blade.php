@include('usercp.sections._menu', ['selected' => 'security'])

<form method=post action=usercp.php id="security"
      data-auth-form="{{ $security['isConfirm'] ? 'challenge' : 'hash' }}"
      data-username-name="username"
      data-password-class="{{ $security['isConfirm'] ? 'oldpassword' : 'password' }}"
      data-password-hash-name="chpassword" data-password-confirm-class="passagain"
      data-tip-short="{{ \App\Support\Locale::trans('signup.password_too_short', [], null) }}"
      data-tip-long="{{ \App\Support\Locale::trans('signup.password_too_long', [], null) }}"
      data-tip-equal-username="{{ \App\Support\Locale::trans('signup.password_equals_username', [], null) }}"
      data-tip-unmatched="{{ \App\Support\Locale::trans('signup.passwords_unmatched', [], null) }}"><input type=hidden name=action value=security><input type=hidden name=type value={{ $security['isConfirm'] ? 'confirm' : 'save' }}>
<div class="nx-fgrid nx-fgrid--flat">
@if ($security['isConfirm'])
@if (($security['confirmHidden']['resetpasskey'] ?? '') === '1')<input type="hidden" name="resetpasskey" value="1">@endif
@if (($security['confirmHidden']['resetauthkey'] ?? '') === '1')<input type="hidden" name="resetauthkey" value="1">@endif
<input type="hidden" name="email" value="{{ $security['confirmHidden']['email'] ?? '' }}">
<input type="hidden" name="chpassword" value="{{ $security['confirmHidden']['chpassword'] ?? '' }}">
<input type="hidden" name="privacy" value="{{ $security['confirmHidden']['privacy'] ?? '' }}">
<input type="hidden" name="two_step_secret" value="{{ $security['confirmHidden']['two_step_secret'] ?? '' }}">
<input type="hidden" name="two_step_code" value="{{ $security['confirmHidden']['two_step_code'] ?? '' }}">
<div class="nx-fhead nx-nowrap">{{ $lang['row_security_check'] ?? 'Check' }}</div><div class="nx-fcell"><input type=password class=oldpassword style="width: 200px"><br /><font class=small>{{ $lang['text_security_check_note'] ?? '' }}</font></div>
<input type=hidden name=username value="{{ (string) ($curUser['username'] ?? '') }}">
<input type=hidden name=response>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($security['confirmHtml'] ?? ''))
<div class="nx-fhead">{{ $lang['row_save_settings'] ?? 'Save' }}</div><div class="nx-fcell"><input type=button value="{{ $lang['submit_save_settings'] ?? 'Save' }}"></div>
</div></form>
@else
@if ($type === 'saved')
<div class="nx-ffull nx-center"><font color=red><b>{{ $security['savedMessage'] }}</b></font></div>
@endif
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($security['rowsHtml'] ?? ''))
<input type="hidden" name="chpassword" />
<div class="nx-fhead">{{ $lang['row_save_settings'] ?? 'Save' }}</div><div class="nx-fcell"><input type=button value="{{ $lang['submit_save_settings'] ?? 'Save' }}"></div>
</div></form>
@endif
