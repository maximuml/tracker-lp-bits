@php
$siteName = \App\Models\Setting::getSiteName();
$headTitle = $isInvite ? ($lang['head_invite_signup'] ?? 'Invite Signup') : ($lang['head_signup'] ?? 'Signup');
$preUsername = $isInvite && $isPreRegisterEmailAndUsername && ! empty($invite->pre_register_username) ? (string) $invite->pre_register_username : '';
$preEmail = $isInvite && $isPreRegisterEmailAndUsername && ! empty($invite->pre_register_email) ? (string) $invite->pre_register_email : '';
@endphp

@extends('layouts.nexus_legacy')

@section('title', $headTitle . ' :: ' . $siteName)

@section('content')
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

    <x-nexus.card :title="$headTitle">
        <form method="get" action="/signup" class="mb-4 text-right">
            @if ($isInvite)
                <input type="hidden" name="type" value="invite" />
                <input type="hidden" name="invitenumber" value="{{ $code }}" />
            @endif
            <input type="hidden" name="secret" value="{{ $secret }}" />
            {{ $lang['text_select_lang'] ?? 'Select Site Language:' }}
            <select name="sitelanguage" onchange="this.form.submit()" class="border border-nexus-border bg-nexus-surface text-nexus-text">
                @foreach ($languages as $row)
                    <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                        {{ $row['lang_name'] }}
                    </option>
                @endforeach
            </select>
        </form>

        <form method="post" action="/signup" id="signup-form">
            @csrf
            @if ($isInvite)
                <input type="hidden" name="inviter" value="{{ $invite->inviter ?? $inviter ?? '' }}" />
                <input type="hidden" name="type" value="invite" />
                <input type="hidden" name="hash" value="{{ $code }}" />
            @endif

            <table class="w-full text-sm">
                <tr>
                    <td class="toolbox text-center" colspan="2">{!! $lang['text_cookies_note'] !!}</td>
                </tr>

                @php
                    $usernameInput = $preUsername !== ''
                        ? '<input type="text" name="wantusername" value="' . e($preUsername) . '" readonly autocomplete="username" class="w-full max-w-xs border border-nexus-border px-2 py-1" />'
                        : '<input type="text" name="wantusername" value="' . e(old('wantusername')) . '" autocomplete="username" class="w-full max-w-xs border border-nexus-border px-2 py-1" />';
                    $emailInput = $preEmail !== ''
                        ? '<input type="email" name="email" value="' . e($preEmail) . '" readonly autocomplete="email" class="w-full max-w-xs border border-nexus-border px-2 py-1" />'
                        : '<input type="email" name="email" value="' . e(old('email')) . '" autocomplete="email" class="w-full max-w-xs border border-nexus-border px-2 py-1" />';
                @endphp

                <tr>
                    <td class="rowhead">{{ $lang['row_desired_username'] ?? 'Desired username' }}</td>
                    <td class="rowfollow">
                        {!! $usernameInput !!}<br />
                        <span class="text-nexus-muted text-xs">{{ $lang['text_allowed_characters'] ?? 'Allowed Characters: (a-z), (A-Z), (0-9), Maximum is 12 characters' }}</span>
                    </td>
                </tr>
                <tr>
                    <td class="rowhead">{{ $lang['row_pick_a_password'] }}</td>
                    <td class="rowfollow">
                        <input type="password" class="wantpassword w-full max-w-xs border border-nexus-border px-2 py-1" autocomplete="new-password" /><br />
                        <span class="text-nexus-muted text-xs">{{ $lang['text_minimum_six_characters'] ?? 'Minimum is 6 characters' }}</span>
                    </td>
                </tr>
                <tr>
                    <td class="rowhead">{{ $lang['row_enter_password_again'] ?? 'Enter password again' }}</td>
                    <td class="rowfollow">
                        <input type="password" class="passagain w-full max-w-xs border border-nexus-border px-2 py-1" autocomplete="new-password" />
                    </td>
                </tr>

                @if ($captchaEnabled && $captchaMarkup !== '')
                    {!! $captchaMarkup !!}
                @endif

                <tr>
                    <td class="rowhead">{{ $lang['row_email_address'] ?? 'Email address' }}</td>
                    <td class="rowfollow">{!! $emailInput !!}</td>
                </tr>

                <tr>
                    <td class="rowhead">{{ $lang['row_country'] ?? 'Country' }}</td>
                    <td class="rowfollow">
                        <select name="country" class="w-full max-w-xs border border-nexus-border bg-nexus-surface px-2 py-1">
                            <option value="8">---- {{ $lang['select_none_selected'] ?? 'None selected' }} ----</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}" @if ((int) old('country', 8) === (int) $country->id) selected @endif>{{ $country->name }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>

                <tr>
                    <td class="rowhead">{{ $lang['row_gender'] ?? 'Gender' }}</td>
                    <td class="rowfollow">
                        <input type="radio" name="gender" value="Male" @if (old('gender') === 'Male') checked @endif />{{ $lang['radio_male'] ?? 'Male ' }}
                        <input type="radio" name="gender" value="Female" @if (old('gender') === 'Female') checked @endif />{{ $lang['radio_female'] ?? 'Female ' }}
                    </td>
                </tr>

                <tr>
                    <td class="rowhead">{{ $lang['row_verification'] ?? 'Verification' }}</td>
                    <td class="rowfollow">
                        <input type="checkbox" name="rulesverify" value="yes" @if (old('rulesverify') === 'yes') checked @endif />{!! $lang['checkbox_read_rules'] ?? 'I have read the site <a href="rules.php"><u>rules</u></a> page.' !!}<br />
                        <input type="checkbox" name="faqverify" value="yes" @if (old('faqverify') === 'yes') checked @endif />{!! $lang['checkbox_read_faq'] ?? 'I agree to read the <a href="faq.php"><u>FAQ</u></a> before asking questions.' !!}<br />
                        <input type="checkbox" name="ageverify" value="yes" @if (old('ageverify') === 'yes') checked @endif />{!! $lang['checkbox_age'] ?? 'I am at least 13 years old.' !!}
                    </td>
                </tr>

                <input type="hidden" name="wantpassword" />

                <tr>
                    <td class="toolbox text-center" colspan="2">
                        <span class="text-nexus-danger font-semibold">{{ $lang['text_all_fields_required'] ?? 'All Fields are required!' }}</span>
                        <x-nexus.button type="button" variant="primary">{!! $lang['submit_sign_up'] ?? 'Sign up!' !!}</x-nexus.button>
                    </td>
                </tr>
            </table>
        </form>

        @php
            ob_start();
            \App\Support\Form::passwordHashJs('signup-form', 'wantpassword', 'wantpassword', true, 'passagain', 'wantusername');
            $passwordHashJs = ob_get_clean();
        @endphp
        {!! $passwordHashJs !!}
    </x-nexus.card>
@endsection
