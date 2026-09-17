@extends('layouts.auth')

@section('title', (__('legacy/login.head_login')) . ' :: ' . $siteName)

@section('content')
    @if (request()->query('status') === 'reset')
        <div class="success">Your password has been reset. Please check your email for the new password.</div>
    @endif

    @if ($error)
        <div class="error">{{ $error }}</div>
    @endif

    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <form method="get" action="/login">
        <input type="hidden" name="secret" value="{{ $secret }}" />
        @if ($returnto !== '')
            <input type="hidden" name="returnto" value="{{ $returnto }}" />
        @endif
        <div align="right">
            {{ __('legacy/login.text_select_lang')}}
            <select name="sitelanguage" aria-label="{{ __('legacy/login.text_select_lang')}}">
                @foreach ($languages as $row)
                    <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                        {{ $row['lang_name'] }}
                    </option>
                @endforeach
            </select>
        </div>
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
        <p>@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/login.p_need_cookies_enables')))<br />
            [<b>{{ $maxAttempts }}</b>] {{ __('legacy/login.p_fail_ban')}}
        </p>
        <p>{{ __('legacy/login.p_you_have')}} <b>{{ $remaining }}</b> {{ __('legacy/login.p_remaining_tries')}}</p>

        <div class="nx-fgrid">
            <div class="nx-fhead">{{ __('legacy/login.rowhead_username')}}</div>
            <div class="nx-fcell"><input type="text" name="username" aria-label="{{ __('legacy/login.rowhead_username')}}" autocomplete="username" value="{{ old('username') }}" /></div>
            <div class="nx-fhead">{{ __('legacy/login.rowhead_password')}}</div>
            <div class="nx-fcell"><input type="password" name="password" aria-label="{{ __('legacy/login.rowhead_password')}}" autocomplete="current-password" /></div>
            <div class="nx-fhead">{{ __('legacy/login.rowhead_two_step_code')}}</div>
            <div class="nx-fcell"><input type="text" name="two_step_code" aria-label="{{ __('legacy/login.rowhead_two_step_code')}}" inputmode="numeric" pattern="[0-9]*" placeholder="{{ __('legacy/login.two_step_code_tooltip')}}" /></div>
            @if ($captchaEnabled && $captchaMarkup !== '')
                @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($captchaMarkup))
            @endif
            <div class="toolbox nx-ffull">
                {{ __('legacy/login.text_auto_logout')}}
                <input type="checkbox" name="logout" value="yes" aria-label="{{ __('legacy/login.checkbox_auto_logout')}}" /> {{ __('legacy/login.checkbox_auto_logout')}}
            </div>
            <div class="toolbox nx-ffull">
                <input type="submit" value="{{ __('legacy/login.button_login')}}" class="btn" />
                <input type="reset" value="{{ __('legacy/login.button_reset')}}" class="btn" />
            </div>
        </div>

        @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($passkeyLoginHtml))
    </form>

    @if ($isComplainEnabled)
        <p>[<b><a href="complains.php">{{ __('legacy/login.text_complain')}}</a></b>]</p>
    @endif

    <p>@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/login.p_no_account_signup')))</p>
    @if ($isSmtpEnabled)
        <p>@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/login.p_forget_pass_recover')))</p>
        <p>@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/login.p_account_banned')))</p>
        <p>@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/login.p_resend_confirm')))</p>
    @endif
@endsection
