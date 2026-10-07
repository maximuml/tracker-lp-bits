@extends('layouts.app', ['chromeVariant' => 'auth'])

@section('title', __('login.head_login'))

@section('content')
    @if (request()->query('status') === 'reset')
        <div class="nx-auth__success">{{ __('recover.text_password_reset_success') }}</div>
    @endif

    @if ($error)
        <div class="nx-auth__error">{{ $error }}</div>
    @endif

    @if ($errors->any())
        <div class="nx-auth__error">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <form method="get" action="/login" class="nx-auth__lang">
        <input type="hidden" name="secret" value="{{ $secret }}" />
        @if ($returnto !== '')
            <input type="hidden" name="returnto" value="{{ $returnto }}" />
        @endif
        <label for="sitelanguage">{{ __('login.text_select_lang')}}</label>
        <select id="sitelanguage" name="sitelanguage">
            @foreach ($languages as $row)
                <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                    {{ $row['lang_name'] }}
                </option>
            @endforeach
        </select>
    </form>

    @if ($showWarn)
        <h1>{{ __('login.h1_not_logged_in')}}</h1>
        <p><b>{{ __('login.p_error')}}</b> {{ __('login.p_after_logged_in')}}</p>
    @endif

    <form id="login-form" method="post" action="/login">
        @csrf
        <input type="hidden" name="secret" value="{{ $secret }}" />
        @if ($returnto !== '')
            <input type="hidden" name="returnto" value="{{ $returnto }}" />
        @endif
        <p><b>{{ __('login.text_note') }}</b>: {{ __('login.p_need_cookies_enables') }}<br />
            [<b>{{ $maxAttempts }}</b>] {{ __('login.p_fail_ban')}}
        </p>
        <p>{{ __('login.p_you_have')}} <b>{{ $remaining }}</b> {{ __('login.p_remaining_tries')}}</p>

        <x-form-field :label="__('login.rowhead_username')" name="username" :value="old('username')" autocomplete="username" />
        <x-form-field :label="__('login.rowhead_password')" name="password" type="password" autocomplete="current-password" />
        <x-form-field :label="__('login.rowhead_two_step_code')" name="two_step_code" inputmode="numeric" pattern="[0-9]*" :placeholder="__('login.two_step_code_tooltip')" />

        @if ($captchaEnabled && $captchaMarkup !== '')
            {{ $captchaMarkup }}
        @endif

        <label class="nx-auth__checkline">
            {{ __('login.text_auto_logout')}}
            <input type="checkbox" name="logout" value="yes" /> {{ __('login.checkbox_auto_logout')}}
        </label>

        <div class="nx-auth__actions">
            <x-button type="submit" variant="primary">{{ __('login.button_login')}}</x-button>
            <x-button type="reset">{{ __('login.button_reset')}}</x-button>
        </div>

        <x-passkey-login />
    </form>

    @if ($isComplainEnabled)
        <p>[<b><a href="/web/complains">{{ __('login.text_complain')}}</a></b>]</p>
    @endif

    <p>{{ __('login.p_no_account_signup') }} <a href="/signup"><b>{{ __('login.text_sign_up') }}</b></a> {{ __('login.p_no_account_signup_end') }}</p>
    @if ($isSmtpEnabled)
        <p>{{ __('login.p_forget_pass_recover') }} <a href="/recover"><b>{{ __('login.text_via_email') }}</b></a></p>
        <p>{{ __('login.p_account_banned') }} <a href="/web/user-ban-log"><b>{{ __('login.text_user_ban_log') }}</b></a></p>
        <p>{{ __('login.p_resend_confirm') }} <a href="/confirm_resend"><b>{{ __('login.text_send_confirmation_again') }}</b></a></p>
    @endif
@endsection
