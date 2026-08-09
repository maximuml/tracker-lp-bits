@php
$siteName = \App\Models\Setting::getSiteName();
$showWarn = $returnto !== '' && ! $nowarn;
@endphp

@extends('layouts.nexus_legacy')

@section('title', ($lang['head_login'] ?? 'Login') . ' :: ' . $siteName)

@section('content')
    @if (request()->query('status') === 'reset')
        <x-nexus.alert variant="success" :title="$lang['std_message'] ?? 'Success'">
            Your password has been reset. Please check your email for the new password.
        </x-nexus.alert>
    @endif

    @if ($error)
        <x-nexus.alert variant="danger" :title="$lang['std_message'] ?? 'Error'">
            {{ $error }}
        </x-nexus.alert>
    @endif

    @if ($errors->any())
        <x-nexus.alert variant="danger" :title="$lang['std_message'] ?? 'Error'">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </x-nexus.alert>
    @endif

    <x-nexus.card :title="$lang['head_login'] ?? 'Login'">
        <form method="get" action="/login" class="mb-4 text-right">
            <input type="hidden" name="secret" value="{{ $secret }}" />
            @if ($returnto !== '')
                <input type="hidden" name="returnto" value="{{ $returnto }}" />
            @endif
            {{ $lang['text_select_lang'] ?? 'Select Site Language:' }}
            <select name="sitelanguage" onchange="this.form.submit()" class="border border-nexus-border bg-nexus-surface text-nexus-text">
                @foreach ($languages as $row)
                    <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                        {{ $row['lang_name'] }}
                    </option>
                @endforeach
            </select>
        </form>

        @if ($showWarn)
            <x-nexus.alert variant="warning" :title="$lang['p_error'] ?? 'Error'">
                {{ $lang['p_after_logged_in'] ?? 'The page you tried to view can only be used when you are logged in.' }}
            </x-nexus.alert>
        @endif

        <form id="login-form" method="post" action="takelogin.php">
            @csrf
            <input type="hidden" name="secret" value="{{ $secret }}" />
            @if ($returnto !== '')
                <input type="hidden" name="returnto" value="{{ $returnto }}" />
            @endif

            <table class="w-full text-sm">
                <tr>
                    <td class="rowhead">{{ $lang['rowhead_username'] ?? 'Username:' }}</td>
                    <td class="rowfollow"><input type="text" name="username" autocomplete="username" value="{{ old('username') }}" class="w-full max-w-xs border border-nexus-border px-2 py-1" /></td>
                </tr>
                <tr>
                    <td class="rowhead">{{ $lang['rowhead_password'] ?? 'Password:' }}</td>
                    <td class="rowfollow"><input type="password" name="password" autocomplete="current-password" class="w-full max-w-xs border border-nexus-border px-2 py-1" /></td>
                </tr>
                <tr>
                    <td class="rowhead">{{ $lang['rowhead_two_step_code'] ?? 'Two-Factor Authentication:' }}</td>
                    <td class="rowfollow"><input type="text" name="two_step_code" inputmode="numeric" pattern="[0-9]*" placeholder="{{ $lang['two_step_code_tooltip'] ?? '' }}" class="w-full max-w-xs border border-nexus-border px-2 py-1" /></td>
                </tr>
                @if ($captchaEnabled && $captchaMarkup !== '')
                    {!! $captchaMarkup !!}
                @endif
                <tr>
                    <td class="toolbox text-center" colspan="2">
                        {{ $lang['text_auto_logout'] ?? 'Auto Logout:' }}
                        <input type="checkbox" name="logout" value="yes" /> {{ $lang['checkbox_auto_logout'] ?? 'Log me out after 15 minutes' }}
                    </td>
                </tr>
                <tr>
                    <td class="toolbox text-center" colspan="2">
                        <x-nexus.button type="submit" variant="primary">{{ $lang['button_login'] ?? 'Login!' }}</x-nexus.button>
                        <x-nexus.button type="reset" variant="secondary">{{ $lang['button_reset'] ?? 'Reset' }}</x-nexus.button>
                    </td>
                </tr>
            </table>

            <p class="mt-4 text-sm">{!! $lang['p_need_cookies_enables'] ?? 'Note: You need cookies enabled to log in or switch language.' !!} [<b>{{ $maxAttempts }}</b>] {{ $lang['p_fail_ban'] ?? 'failed logins in a row will result in banning your ip!' }}</p>
            <p class="text-sm">{{ $lang['p_you_have'] ?? 'You have' }} <b>{{ $remaining }}</b> {{ $lang['p_remaining_tries'] ?? 'remaining tries.' }}</p>
        </form>

        {!! $passkeyLoginHtml !!}

        @if ($oauthProviders->isNotEmpty())
            <p class="mt-4 text-sm">
                {{ $lang['other_methods'] ?? 'Other methods' }}:
                @foreach ($oauthProviders as $oauthProvider)
                    [<b><a href="oauth/redirect/{{ $oauthProvider->uuid }}" class="text-nexus-link hover:underline">{{ $oauthProvider->name }}</a></b>]
                    @if (! $loop->last)&nbsp;&nbsp;@endif
                @endforeach
            </p>
        @endif

        @if ($isComplainEnabled)
            <p class="mt-2 text-sm">[<b><a href="complains.php" class="text-nexus-link hover:underline">{{ $lang['text_complain'] ?? 'Complain' }}</a></b>]</p>
        @endif

        @php
            $isSmtpEnabled = (\App\Models\Setting::get('main.smtptype') ?? 'none') !== 'none';
        @endphp

        <p class="mt-4 text-sm">{!! $lang['p_no_account_signup'] ?? 'Don\'t have an account? Sign up!' !!}</p>
        @if ($isSmtpEnabled)
            <p class="text-sm">{!! $lang['p_forget_pass_recover'] ?? 'Forget your password? Recover via email' !!}</p>
            <p class="text-sm">{!! $lang['p_account_banned'] ?? 'Account banned? View user ban log' !!}</p>
            <p class="text-sm">{!! $lang['p_resend_confirm'] ?? 'Did not receive confirmation mail? Send again' !!}</p>
        @endif
    </x-nexus.card>
@endsection
