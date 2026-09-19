@extends('layouts.auth')

@section('title', __('legacy/login.head_login'))

@section('content')
    @if (request()->query('status') === 'reset')
        <div class="nx-auth__success">Your password has been reset. Please check your email for the new password.</div>
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
        <label for="sitelanguage">{{ __('legacy/login.text_select_lang')}}</label>
        <select id="sitelanguage" name="sitelanguage">
            @foreach ($languages as $row)
                <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                    {{ $row['lang_name'] }}
                </option>
            @endforeach
        </select>
    </form>

    @if ($showWarn)
        <h1>{{ __('legacy/login.h1_not_logged_in')}}</h1>
        <p><b>{{ __('legacy/login.p_error')}}</b> {{ __('legacy/login.p_after_logged_in')}}</p>
    @endif

    <form id="login-form" method="post" action="/login">
        @csrf
        <input type="hidden" name="secret" value="{{ $secret }}" />
        @if ($returnto !== '')
            <input type="hidden" name="returnto" value="{{ $returnto }}" />
        @endif
        <p><b>{{ __('legacy/login.text_note') }}</b>: {{ __('legacy/login.p_need_cookies_enables') }}<br />
            [<b>{{ $maxAttempts }}</b>] {{ __('legacy/login.p_fail_ban')}}
        </p>
        <p>{{ __('legacy/login.p_you_have')}} <b>{{ $remaining }}</b> {{ __('legacy/login.p_remaining_tries')}}</p>

        <x-form-field :label="__('legacy/login.rowhead_username')" name="username" :value="old('username')" autocomplete="username" />
        <x-form-field :label="__('legacy/login.rowhead_password')" name="password" type="password" autocomplete="current-password" />
        <x-form-field :label="__('legacy/login.rowhead_two_step_code')" name="two_step_code" inputmode="numeric" pattern="[0-9]*" :placeholder="__('legacy/login.two_step_code_tooltip')" />

        @if ($captchaEnabled && $captchaMarkup !== '')
            {{ $captchaMarkup }}
        @endif

        <label class="nx-auth__checkline">
            {{ __('legacy/login.text_auto_logout')}}
            <input type="checkbox" name="logout" value="yes" /> {{ __('legacy/login.checkbox_auto_logout')}}
        </label>

        <div class="nx-auth__actions">
            <x-button type="submit" variant="primary">{{ __('legacy/login.button_login')}}</x-button>
            <x-button type="reset">{{ __('legacy/login.button_reset')}}</x-button>
        </div>

        {{ $passkeyLoginHtml }}
    </form>

    @if ($isComplainEnabled)
        <p>[<b><a href="complains.php">{{ __('legacy/login.text_complain')}}</a></b>]</p>
    @endif

    <p>{{ __('legacy/login.p_no_account_signup') }} <a href="signup.php"><b>{{ __('legacy/login.text_sign_up') }}</b></a> {{ __('legacy/login.p_no_account_signup_end') }}</p>
    @if ($isSmtpEnabled)
        <p>{{ __('legacy/login.p_forget_pass_recover') }} <a href="recover.php"><b>{{ __('legacy/login.text_via_email') }}</b></a></p>
        <p>{{ __('legacy/login.p_account_banned') }} <a href="user-ban-log.php"><b>{{ __('legacy/login.text_user_ban_log') }}</b></a></p>
        <p>{{ __('legacy/login.p_resend_confirm') }} <a href="confirm_resend.php"><b>{{ __('legacy/login.text_send_confirmation_again') }}</b></a></p>
    @endif
@endsection
