@include('usercp.sections._menu', ['selected' => 'security'])

<form method=post action=usercp.php id="security"
      data-auth-form="{{ $security->isConfirm ? 'challenge' : 'hash' }}"
      data-username-name="username"
      data-password-class="{{ $security->isConfirm ? 'oldpassword' : 'password' }}"
      data-password-hash-name="chpassword" data-password-confirm-class="passagain"
      data-tip-short="{{ \App\Support\Locale::trans('signup.password_too_short', [], null) }}"
      data-tip-long="{{ \App\Support\Locale::trans('signup.password_too_long', [], null) }}"
      data-tip-equal-username="{{ \App\Support\Locale::trans('signup.password_equals_username', [], null) }}"
      data-tip-unmatched="{{ \App\Support\Locale::trans('signup.passwords_unmatched', [], null) }}"><input type=hidden name=action value=security><input type=hidden name=type value={{ $security->isConfirm ? 'confirm' : 'save' }}>
<div class="nx-fgrid nx-fgrid--flat">
@if ($security->isConfirm)
@if (($security->confirmHidden['resetpasskey'] ?? '') === '1')<input type="hidden" name="resetpasskey" value="1">@endif
@if (($security->confirmHidden['resetauthkey'] ?? '') === '1')<input type="hidden" name="resetauthkey" value="1">@endif
<input type="hidden" name="email" value="{{ $security->confirmHidden['email'] ?? '' }}">
<input type="hidden" name="chpassword" value="{{ $security->confirmHidden['chpassword'] ?? '' }}">
<input type="hidden" name="privacy" value="{{ $security->confirmHidden['privacy'] ?? '' }}">
<input type="hidden" name="two_step_secret" value="{{ $security->confirmHidden['two_step_secret'] ?? '' }}">
<input type="hidden" name="two_step_code" value="{{ $security->confirmHidden['two_step_code'] ?? '' }}">
<div class="nx-fhead whitespace-nowrap">{{ __('legacy/usercp.row_security_check')}}</div><div class="nx-fcell"><input type=password class=oldpassword><br /><span class="small"><b>{{ __('legacy/usercp.text_note') }}</b> {{ __('legacy/usercp.text_security_check_note') }}</span></div>
<input type=hidden name=username value="{{ (string) ($curUser['username'] ?? '') }}">
<input type=hidden name=response>
{{ $security->confirmHtml }}
<div class="nx-fhead">{{ __('legacy/usercp.row_save_settings')}}</div><div class="nx-fcell"><input type=button value="{{ __('legacy/usercp.submit_save_settings')}}"></div>
</div></form>
@else
@if ($type === 'saved')
<div class="nx-ffull text-center"><span class="text-nxm-danger"><b>{{ $security->savedMessage }}</b></span></div>
@endif
<x-settings-row-small layout="grid" :label="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/usercp.row_reset_passkey'))"><input type="checkbox" name="resetpasskey" value="1" />{{ __('legacy/usercp.checkbox_reset_my_passkey') }}<br /><span class="small"><b>{{ __('legacy/usercp.text_note') }}</b> {{ __('legacy/usercp.text_reset_passkey_note') }}</span></x-settings-row-small>
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_two_step_secret')">@if ($security->twoStep->hasSecret)<input type="text" name="two_step_code" />{{ __('legacy/usercp.text_two_step_secret_unbind_note') }}@else<div class="nx-tfa-row"><div><img src="{{ $security->twoStep->qrCodeUrl }}" /></div><div>{{ __('legacy/usercp.text_two_step_secret_bind_by_qrdoe_note') }}<br/><br/>{{ __('legacy/usercp.text_two_step_qr_fail_note') }}<a href="{{ $security->twoStep->qrCodeUrl }}" target="_blank">Link</a><br /><br />{{ __('legacy/usercp.text_two_step_secret_bind_manually_note') }}{{ $security->twoStep->secret }}<br/><br/>{{ __('legacy/usercp.text_two_step_secret_bind_complete_note') }}<input type="hidden" name="two_step_secret" value="{{ $security->twoStep->secret }}" /><input type="text" name="two_step_code" readonly /></div></div><script @if ($security->cspNonce !== '') nonce="{{ $security->cspNonce }}"@endif>document.addEventListener("focusin",function(e){if(e.target&&e.target.name==="two_step_code"){e.target.removeAttribute("readonly")}})</script>@endif</x-settings-row-small>
<div class="nx-fhead whitespace-nowrap">{{ \App\Support\Locale::trans('passkey.passkey', [], null) }}</div><div class="nx-fcell">@include('usercp.sections._passkeys')</div>
@if ($security->showEmailChange)
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_email_address')"><input type="text" name="email" value="{{ $security->email }}" /> <br /><span class="small"><b>{{ __('legacy/usercp.text_note') }}</b> {{ __('legacy/usercp.text_email_address_note') }}</span></x-settings-row-small>
@endif
<x-settings-row-small layout="grid" :label="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/usercp.row_change_password'))"><input type="password" class="password" /></x-settings-row-small>
<x-settings-row-small layout="grid" :label="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/usercp.row_type_password_again'))"><input type="password" class="passagain" /></x-settings-row-small>
<x-settings-radios layout="grid" :label="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/usercp.row_privacy_level'))" name="privacy" :options="['normal' => __('legacy/usercp.radio_normal'), 'low' => __('legacy/usercp.radio_low'), 'strong' => __('legacy/usercp.radio_strong')]" :selected="$security->privacy" />
<input type="hidden" name="chpassword" />
<div class="nx-fhead">{{ __('legacy/usercp.row_save_settings')}}</div><div class="nx-fcell"><input type=button value="{{ __('legacy/usercp.submit_save_settings')}}"></div>
</div></form>
<form method="post" action="{{ route('usercp.logout-all') }}">
@csrf
<div class="nx-fgrid nx-fgrid--flat">
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_sessions')"><button type="submit">{{ __('legacy/usercp.submit_logout_all_devices') }}</button><br /><span class="small">{{ __('legacy/usercp.text_logout_all_devices_note') }}</span></x-settings-row-small>
</div>
</form>
@endif
