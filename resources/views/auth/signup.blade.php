@php
$siteName = \App\Models\Setting::getSiteName();
$headTitle = $isInvite ? ($lang['head_invite_signup'] ?? 'Invite Signup') : ($lang['head_signup'] ?? 'Signup');
$preUsername = $isInvite && $isPreRegisterEmailAndUsername && ! empty($invite->pre_register_username) ? (string) $invite->pre_register_username : '';
$preEmail = $isInvite && $isPreRegisterEmailAndUsername && ! empty($invite->pre_register_email) ? (string) $invite->pre_register_email : '';
@endphp

@extends('layouts.auth')

@section('title', $headTitle . ' :: ' . $siteName)

@section('content')
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

    <form method="get" action="/signup">
        @if ($isInvite)
            <input type="hidden" name="type" value="invite" />
            <input type="hidden" name="invitenumber" value="{{ $code }}" />
        @endif
        <input type="hidden" name="secret" value="{{ $secret }}" />
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

    <p>
    <form method="post" action="/signup" id="signup-form">
        @csrf
        @if ($isInvite)
            <input type="hidden" name="inviter" value="{{ $invite->inviter ?? $inviter ?? '' }}" />
            <input type="hidden" name="type" value="invite" />
            <input type="hidden" name="hash" value="{{ $code }}" />
        @endif

        <table border="1" cellspacing="0" cellpadding="10" style="width: 100%;">
            <tr><td class="toolbox" align="center" colspan="2">{!! $lang['text_cookies_note'] !!}</td></tr>

            @php
                $inputStyle = 'style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box"';
                $usernameInput = $preUsername !== ''
                    ? '<input type="text" ' . $inputStyle . ' name="wantusername" value="' . e($preUsername) . '" readonly autocomplete="username" />'
                    : '<input type="text" ' . $inputStyle . ' name="wantusername" value="' . e(old('wantusername')) . '" autocomplete="username" />';
                $emailInput = $preEmail !== ''
                    ? '<input type="email" ' . $inputStyle . ' name="email" value="' . e($preEmail) . '" readonly autocomplete="email" />'
                    : '<input type="email" ' . $inputStyle . ' name="email" value="' . e(old('email')) . '" autocomplete="email" />';
            @endphp

            <tr>
                <td class="rowhead">{{ $lang['row_desired_username'] ?? 'Desired username' }}</td>
                <td class="rowfollow" align="left">
                    {!! $usernameInput !!}<br />
                    <font class="small">{{ $lang['text_allowed_characters'] ?? 'Allowed Characters: (a-z), (A-Z), (0-9), Maximum is 12 characters' }}</font>
                </td>
            </tr>
            <tr>
                <td class="rowhead">{{ $lang['row_pick_a_password'] }}</td>
                <td class="rowfollow" align="left">
                    <input type="password" {!! $inputStyle !!} class="wantpassword" autocomplete="new-password" /><br />
                    <font class="small">{{ $lang['text_minimum_six_characters'] ?? 'Minimum is 6 characters' }}</font>
                </td>
            </tr>
            <tr>
                <td class="rowhead">{{ $lang['row_enter_password_again'] ?? 'Enter password again' }}</td>
                <td class="rowfollow" align="left">
                    <input type="password" {!! $inputStyle !!} class="passagain" autocomplete="new-password" />
                </td>
            </tr>

            @if ($captchaEnabled && $captchaMarkup !== '')
                {!! $captchaMarkup !!}
            @endif

            <tr>
                <td class="rowhead">{{ $lang['row_email_address'] ?? 'Email address' }}</td>
                <td class="rowfollow" align="left">{!! $emailInput !!}</td>
            </tr>

            <tr>
                <td class="rowhead">{{ $lang['row_country'] ?? 'Country' }}</td>
                <td class="rowfollow" align="left">
                    <select name="country" style="width: min(100%, 320px);">
                        <option value="8">---- {{ $lang['select_none_selected'] ?? 'None selected' }} ----</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}" @if ((int) old('country', 8) === (int) $country->id) selected @endif>{{ $country->name }}</option>
                        @endforeach
                    </select>
                </td>
            </tr>

            <tr>
                <td class="rowhead">{{ $lang['row_gender'] ?? 'Gender' }}</td>
                <td class="rowfollow" align="left">
                    <input type="radio" name="gender" value="Male" @if (old('gender') === 'Male') checked @endif />{{ $lang['radio_male'] ?? 'Male ' }}
                    <input type="radio" name="gender" value="Female" @if (old('gender') === 'Female') checked @endif />{{ $lang['radio_female'] ?? 'Female ' }}
                </td>
            </tr>

            <tr>
                <td class="rowhead">{{ $lang['row_verification'] ?? 'Verification' }}</td>
                <td class="rowfollow" align="left">
                    <input type="checkbox" name="rulesverify" value="yes" @if (old('rulesverify') === 'yes') checked @endif />{!! $lang['checkbox_read_rules'] ?? 'I have read the site <a href="rules.php"><u>rules</u></a> page.' !!}<br />
                    <input type="checkbox" name="faqverify" value="yes" @if (old('faqverify') === 'yes') checked @endif />{!! $lang['checkbox_read_faq'] ?? 'I agree to read the <a href="faq.php"><u>FAQ</u></a> before asking questions.' !!}<br />
                    <input type="checkbox" name="ageverify" value="yes" @if (old('ageverify') === 'yes') checked @endif />{!! $lang['checkbox_age'] ?? 'I am at least 13 years old.' !!}
                </td>
            </tr>

            <input type="hidden" name="wantpassword" />

            <tr>
                <td class="toolbox" colspan="2" align="center">
                    <font color="red"><b>{{ $lang['text_all_fields_required'] ?? 'All Fields are required!' }}</b></font><p></p>
                    <input id="submit-btn" type="button" value="{!! $lang['submit_sign_up'] ?? 'Sign up!' !!}" style="height: 25px" />
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
@endsection
