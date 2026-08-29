@php
$siteName = \App\Models\Setting::getSiteName();
$showWarn = $returnto !== '' && ! $nowarn;
@endphp

@extends('layouts.auth')

@section('title', ($lang['head_login'] ?? 'Login') . ' :: ' . $siteName)

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
            {{ $lang['text_select_lang'] ?? 'Select Site Language:' }}
            <select name="sitelanguage" onchange="this.form.submit()">
                @foreach ($languages as $row)
                    <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                        {{ $row['lang_name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    @if ($showWarn)
        <h1>{{ $lang['h1_not_logged_in'] ?? 'Not logged in!' }}</h1>
        <p><b>{{ $lang['p_error'] ?? 'Error:' }}</b> {{ $lang['p_after_logged_in'] ?? 'The page you tried to view can only be used when you are logged in.' }}</p>
    @endif

    <form id="login-form" method="post" action="/login">
        @csrf
        <input type="hidden" name="secret" value="{{ $secret }}" />
        @if ($returnto !== '')
            <input type="hidden" name="returnto" value="{{ $returnto }}" />
        @endif
        <p>{!! $lang['p_need_cookies_enables'] ?? 'Note: You need cookies enabled to log in or switch language.' !!}<br />
            [<b>{{ $maxAttempts }}</b>] {{ $lang['p_fail_ban'] ?? 'failed logins in a row will result in banning your ip!' }}
        </p>
        <p>{{ $lang['p_you_have'] ?? 'You have' }} <b>{{ $remaining }}</b> {{ $lang['p_remaining_tries'] ?? 'remaining tries.' }}</p>

        <table border="0" cellpadding="5">
            <tr>
                <td class="rowhead">{{ $lang['rowhead_username'] ?? 'Username:' }}</td>
                <td class="rowfollow"><input type="text" name="username" autocomplete="username" value="{{ old('username') }}" /></td>
            </tr>
            <tr>
                <td class="rowhead">{{ $lang['rowhead_password'] ?? 'Password:' }}</td>
                <td class="rowfollow"><input type="password" name="password" autocomplete="current-password" /></td>
            </tr>
            <tr>
                <td class="rowhead">{{ $lang['rowhead_two_step_code'] ?? 'Two-Factor Authentication:' }}</td>
                <td class="rowfollow"><input type="text" name="two_step_code" inputmode="numeric" pattern="[0-9]*" placeholder="{{ $lang['two_step_code_tooltip'] ?? '' }}" /></td>
            </tr>
            @if ($captchaEnabled && $captchaMarkup !== '')
                {!! $captchaMarkup !!}
            @endif
            <tr>
                <td class="toolbox" colspan="2">
                    {{ $lang['text_auto_logout'] ?? 'Auto Logout:' }}
                    <input type="checkbox" name="logout" value="yes" /> {{ $lang['checkbox_auto_logout'] ?? 'Log me out after 15 minutes' }}
                </td>
            </tr>
            <tr>
                <td class="toolbox" colspan="2" align="center">
                    <input type="submit" value="{{ $lang['button_login'] ?? 'Login!' }}" class="btn" />
                    <input type="reset" value="{{ $lang['button_reset'] ?? 'Reset' }}" class="btn" />
                </td>
            </tr>
        </table>

        {!! $passkeyLoginHtml !!}
    </form>

    @if ($isComplainEnabled)
        <p>[<b><a href="complains.php">{{ $lang['text_complain'] ?? 'Complain' }}</a></b>]</p>
    @endif

    @php
        $isSmtpEnabled = \App\Support\Config\SiteConfig::current()->smtp->type() !== 'none';
    @endphp

    <p>{!! $lang['p_no_account_signup'] ?? 'Don\'t have an account? Sign up!' !!}</p>
    @if ($isSmtpEnabled)
        <p>{!! $lang['p_forget_pass_recover'] ?? 'Forget your password? Recover via email' !!}</p>
        <p>{!! $lang['p_account_banned'] ?? 'Account banned? View user ban log' !!}</p>
        <p>{!! $lang['p_resend_confirm'] ?? 'Did not receive confirmation mail? Send again' !!}</p>
    @endif
@endsection
