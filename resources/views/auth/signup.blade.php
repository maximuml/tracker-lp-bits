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
            <select name="sitelanguage" aria-label="{{ $lang['text_select_lang'] ?? 'Select Site Language' }}">
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

        <div class="nx-fgrid nx-fgrid--b">
            <div class="toolbox nx-ffull">@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_cookies_note']))</div>

            <div class="nx-fhead">{{ $lang['row_desired_username'] ?? 'Desired username' }}</div>
            <div class="nx-fcell">
                @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($usernameInput))<br />
                <font class="small">{{ $lang['text_allowed_characters'] ?? 'Allowed Characters: (a-z), (A-Z), (0-9), Maximum is 12 characters' }}</font>
            </div>
            <div class="nx-fhead">{{ $lang['row_pick_a_password'] }}</div>
            <div class="nx-fcell">
                <input type="password" style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" class="wantpassword" aria-label="{{ $lang['row_pick_a_password'] ?? 'Pick a password' }}" autocomplete="new-password" /><br />
                <font class="small">{{ $lang['text_minimum_six_characters'] ?? 'Minimum is 6 characters' }}</font>
            </div>
            <div class="nx-fhead">{{ $lang['row_enter_password_again'] ?? 'Enter password again' }}</div>
            <div class="nx-fcell">
                <input type="password" style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" class="passagain" aria-label="{{ $lang['row_enter_password_again'] ?? 'Enter password again' }}" autocomplete="new-password" />
            </div>

            @if ($captchaEnabled && $captchaMarkup !== '')
                @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($captchaMarkup))
            @endif

            <div class="nx-fhead">{{ $lang['row_email_address'] ?? 'Email address' }}</div>
            <div class="nx-fcell">@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($emailInput))</div>

            <div class="nx-fhead">{{ $lang['row_country'] ?? 'Country' }}</div>
            <div class="nx-fcell">
                <select name="country" aria-label="{{ $lang['row_country'] ?? 'Country' }}" style="width: min(100%, 320px);">
                    <option value="8">---- {{ $lang['select_none_selected'] ?? 'None selected' }} ----</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @if ((int) old('country', 8) === (int) $country->id) selected @endif>{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="nx-fhead">{{ $lang['row_gender'] ?? 'Gender' }}</div>
            <div class="nx-fcell">
                <input type="radio" name="gender" value="Male" aria-label="{{ $lang['radio_male'] ?? 'Male' }}" @if (old('gender') === 'Male') checked @endif />{{ $lang['radio_male'] ?? 'Male ' }}
                <input type="radio" name="gender" value="Female" aria-label="{{ $lang['radio_female'] ?? 'Female' }}" @if (old('gender') === 'Female') checked @endif />{{ $lang['radio_female'] ?? 'Female ' }}
            </div>

            <div class="nx-fhead">{{ $lang['row_verification'] ?? 'Verification' }}</div>
            <div class="nx-fcell">
                <input type="checkbox" name="rulesverify" value="yes" aria-label="{{ $lang['checkbox_read_rules_plain'] ?? 'I have read the site rules page' }}" @if (old('rulesverify') === 'yes') checked @endif />@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['checkbox_read_rules'] ?? 'I have read the site <a href="rules.php"><u>rules</u></a> page.'))<br />
                <input type="checkbox" name="faqverify" value="yes" aria-label="{{ $lang['checkbox_read_faq_plain'] ?? 'I agree to read the FAQ before asking questions' }}" @if (old('faqverify') === 'yes') checked @endif />@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['checkbox_read_faq'] ?? 'I agree to read the <a href="faq.php"><u>FAQ</u></a> before asking questions.'))<br />
                <input type="checkbox" name="ageverify" value="yes" aria-label="{{ $lang['checkbox_age'] ?? 'I am at least 13 years old' }}" @if (old('ageverify') === 'yes') checked @endif />@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['checkbox_age'] ?? 'I am at least 13 years old.'))
            </div>

            <div class="toolbox nx-ffull">
                <font color="#a00"><b>{{ $lang['text_all_fields_required'] ?? 'All Fields are required!' }}</b></font><p></p>
                <input id="submit-btn" type="button" value="@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['submit_sign_up'] ?? 'Sign up!'))" style="height: 25px" />
            </div>
        </div>
        <input type="hidden" name="wantpassword" />
        <input type="hidden" name="passagain" />
    </form>

    @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($passwordHashJs))
@endsection
