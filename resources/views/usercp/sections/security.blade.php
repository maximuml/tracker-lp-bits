@include('components.usercp.sections.menu', ['selected' => 'security'])

@if (! $security->isConfirm && $type === 'saved')
    <x-alert type="success">{{ $security->savedMessage }}</x-alert>
@endif

<div class="nx-setcards">
    <form method=post action="{{ $security->isConfirm ? '/web/usercp/security/confirm' : '/web/usercp/security' }}" id="security"
          data-auth-form="{{ $security->isConfirm ? 'challenge' : 'hash' }}"
          data-username-name="username"
          data-password-class="{{ $security->isConfirm ? 'oldpassword' : 'password' }}"
          data-password-hash-name="chpassword" data-password-confirm-class="passagain"
          data-tip-short="{{ \App\Support\Locale::trans('signup.password_too_short', [], null) }}"
          data-tip-long="{{ \App\Support\Locale::trans('signup.password_too_long', [], null) }}"
          data-tip-equal-username="{{ \App\Support\Locale::trans('signup.password_equals_username', [], null) }}"
          data-tip-unmatched="{{ \App\Support\Locale::trans('signup.passwords_unmatched', [], null) }}" class="nx-setcards__form">
        <input type=hidden name=action value=security>
        <input type=hidden name=type value={{ $security->isConfirm ? 'confirm' : 'save' }}>
        @if ($security->isConfirm)
            @if (($security->confirmHidden['resetpasskey'] ?? '') === '1')<input type="hidden" name="resetpasskey" value="1">@endif
            @if (($security->confirmHidden['resetauthkey'] ?? '') === '1')<input type="hidden" name="resetauthkey" value="1">@endif
            <input type="hidden" name="email" value="{{ $security->confirmHidden['email'] ?? '' }}">
            <input type="hidden" name="chpassword" value="{{ $security->confirmHidden['chpassword'] ?? '' }}">
            <input type="hidden" name="privacy" value="{{ $security->confirmHidden['privacy'] ?? '' }}">
            <input type="hidden" name="two_step_secret" value="{{ $security->confirmHidden['two_step_secret'] ?? '' }}">
            <input type="hidden" name="two_step_code" value="{{ $security->confirmHidden['two_step_code'] ?? '' }}">
            <section class="nx-ucard nx-setcard" aria-labelledby="ucp-sec-confirm">
                <h2 class="nx-setcard__title" id="ucp-sec-confirm">{{ __('usercp.section_confirm') }}</h2>
                <p class="nx-setcard__hint">{{ __('usercp.text_security_confirm_hint') }}</p>
                <div class="nx-setrow">
                    <label class="nx-setrow__label" for="usercp-oldpassword">{{ __('usercp.row_security_check') }}</label>
                    <div class="nx-setrow__control">
                        <input type="password" class="oldpassword" id="usercp-oldpassword">
                        <p class="nx-setrow__hint">{{ __('usercp.text_note') }} {{ __('usercp.text_security_check_note') }}</p>
                    </div>
                </div>
            </section>
            <input type=hidden name=username value="{{ (string) ($curUser['username'] ?? '') }}">
            <input type=hidden name=response>
            {{ $security->confirmHtml }}
        @else
            <input type="hidden" name="chpassword">
            <section class="nx-ucard nx-setcard" aria-labelledby="ucp-sec-password">
                <h2 class="nx-setcard__title" id="ucp-sec-password">{{ __('usercp.section_password') }}</h2>
                <p class="nx-setcard__hint">{{ __('usercp.text_security_confirm_hint') }}</p>
                @if ($security->showEmailChange)
                    <div class="nx-setrow">
                        <label class="nx-setrow__label" for="usercp-email">{{ __('usercp.row_email_address') }}</label>
                        <div class="nx-setrow__control">
                            <input type="text" name="email" id="usercp-email" value="{{ $security->email }}">
                            <p class="nx-setrow__hint">{{ __('usercp.text_note') }} {{ __('usercp.text_email_address_note') }}</p>
                        </div>
                    </div>
                @endif
                <div class="nx-setrow">
                    <label class="nx-setrow__label" for="usercp-password">@safeHtml(\App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.row_change_password')))</label>
                    <div class="nx-setrow__control">
                        <input type="password" class="password" id="usercp-password">
                    </div>
                </div>
                <div class="nx-setrow">
                    <label class="nx-setrow__label" for="usercp-passagain">@safeHtml(\App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.row_type_password_again')))</label>
                    <div class="nx-setrow__control">
                        <input type="password" class="passagain" id="usercp-passagain">
                    </div>
                </div>
            </section>
            <section class="nx-ucard nx-setcard" aria-labelledby="ucp-sec-privacy">
                <h2 class="nx-setcard__title" id="ucp-sec-privacy">{{ __('usercp.section_privacy') }}</h2>
                <p class="nx-setcard__hint">{{ __('usercp.text_privacy_hint') }}</p>
                <div class="nx-setrow" role="group" aria-labelledby="ucp-g-privacy">
                    <div class="nx-setrow__label" id="ucp-g-privacy">@safeHtml(\App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.row_privacy_level')))</div>
                    <div class="nx-setrow__control">
                        <label><input type="radio" name="privacy" value="normal" @checked($security->privacy === 'normal')>{{ __('usercp.radio_normal') }}</label>
                        <label><input type="radio" name="privacy" value="low" @checked($security->privacy === 'low')>{{ __('usercp.radio_low') }}</label>
                        <label><input type="radio" name="privacy" value="strong" @checked($security->privacy === 'strong')>{{ __('usercp.radio_strong') }}</label>
                    </div>
                </div>
            </section>
            <section class="nx-ucard nx-setcard nx-setcard--wide" aria-labelledby="ucp-sec-two-step">
                <h2 class="nx-setcard__title" id="ucp-sec-two-step">{{ __('usercp.section_two_step') }}</h2>
                @if ($security->twoStep->hasSecret)
                    <div class="nx-setrow">
                        <label class="nx-setrow__label" for="usercp-two-step-code">{{ __('usercp.row_two_step_secret') }}</label>
                        <div class="nx-setrow__control">
                            <input type="text" name="two_step_code" id="usercp-two-step-code">
                            <p class="nx-setrow__hint">{{ __('usercp.text_two_step_secret_unbind_note') }}</p>
                        </div>
                    </div>
                @else
                    <div class="nx-tfa">
                        <img class="nx-tfa__qr" src="{{ $security->twoStep->qrCodeUrl }}" alt="QR code">
                        <ol class="nx-tfa__steps">
                            <li>{{ __('usercp.text_two_step_secret_bind_by_qrdoe_note') }}</li>
                            <li>{{ __('usercp.text_two_step_qr_fail_note') }} <a href="{{ $security->twoStep->qrCodeUrl }}" target="_blank" rel="noopener">Link</a></li>
                            <li>{{ __('usercp.text_two_step_secret_bind_manually_note') }} <code class="nx-tfa__secret">{{ $security->twoStep->secret }}</code></li>
                            <li>
                                {{ __('usercp.text_two_step_secret_bind_complete_note') }}
                                <input type="hidden" name="two_step_secret" value="{{ $security->twoStep->secret }}">
                                <input type="text" name="two_step_code" id="usercp-two-step-code" aria-label="{{ __('usercp.text_two_step_secret_bind_complete_note') }}" readonly>
                            </li>
                        </ol>
                    </div>
                    <script @if ($security->cspNonce !== '') nonce="{{ $security->cspNonce }}"@endif>document.addEventListener("focusin",function(e){if(e.target&&e.target.name==="two_step_code"){e.target.removeAttribute("readonly")}})</script>
                @endif
            </section>
        @endif
    </form>

    @unless ($security->isConfirm)
        <section class="nx-ucard nx-setcard nx-setcard--wide" aria-labelledby="ucp-sec-passkeys">
            <h2 class="nx-setcard__title" id="ucp-sec-passkeys">{{ \App\Support\Locale::trans('passkey.passkey', [], null) }}</h2>
            @include('components.usercp.sections.passkeys')
        </section>
        <section class="nx-ucard nx-setcard nx-setcard--wide" aria-labelledby="ucp-sec-danger">
            <h2 class="nx-setcard__title" id="ucp-sec-danger">{{ __('usercp.section_danger') }}</h2>
            <p class="nx-setcard__hint">{{ __('usercp.text_danger_hint') }}</p>
            <div class="nx-setrow">
                <div class="nx-setrow__label">@safeHtml(\App\Support\Html\SafeHtml::fromUntrustedHtml(__('usercp.row_reset_passkey')))</div>
                <div class="nx-setrow__control">
                    <label><input type="checkbox" name="resetpasskey" value="1" form="security" id="usercp-resetpasskey">{{ __('usercp.checkbox_reset_my_passkey') }}</label>
                    <p class="nx-setrow__hint">{{ __('usercp.text_note') }} {{ __('usercp.text_reset_passkey_note') }}</p>
                </div>
            </div>
            <div class="nx-setrow">
                <div class="nx-setrow__label">{{ __('usercp.row_sessions') }}</div>
                <div class="nx-setrow__control">
                    <button type="submit" form="usercp-logout-all" class="nx-btn">{{ __('usercp.submit_logout_all_devices') }}</button>
                    <p class="nx-setrow__hint">{{ __('usercp.text_logout_all_devices_note') }}</p>
                </div>
            </div>
        </section>
    @endunless
</div>

<div class="nx-savebar">
    <button type="button" form="security" class="nx-btn nx-btn--primary">{{ $security->isConfirm ? __('usercp.submit_confirm_save') : __('usercp.submit_save') }}</button>
    @unless ($security->isConfirm)
        <span class="nx-savebar__hint">{{ __('usercp.text_security_confirm_hint') }}</span>
    @endunless
</div>

@unless ($security->isConfirm)
    <form id="usercp-logout-all" method="post" action="{{ route('usercp.logout-all') }}">@csrf</form>
@endunless
