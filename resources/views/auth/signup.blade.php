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
            {{ __('legacy/signup.text_select_lang')}}
            <select name="sitelanguage" aria-label="{{ __('legacy/signup.text_select_lang')}}">
                @foreach ($languages as $row)
                    <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                        {{ $row['lang_name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    <p>
    <form method="post" action="/signup" id="signup-form"
          data-auth-form="hash" data-username-name="wantusername"
          data-password-class="wantpassword" data-password-hash-name="wantpassword"
          data-password-confirm-class="passagain" data-password-required="1"
          data-tip-short="{{ \App\Support\Locale::trans('signup.password_too_short', [], null) }}"
          data-tip-long="{{ \App\Support\Locale::trans('signup.password_too_long', [], null) }}"
          data-tip-equal-username="{{ \App\Support\Locale::trans('signup.password_equals_username', [], null) }}"
          data-tip-unmatched="{{ \App\Support\Locale::trans('signup.passwords_unmatched', [], null) }}">
        @csrf
        @if ($isInvite)
            <input type="hidden" name="inviter" value="{{ $invite->inviter ?? $inviter ?? '' }}" />
            <input type="hidden" name="type" value="invite" />
            <input type="hidden" name="hash" value="{{ $code }}" />
        @endif

        <div class="nx-fgrid nx-fgrid--b">
            <div class="toolbox nx-ffull">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/signup.text_cookies_note')))</div>

            <div class="nx-fhead">{{ __('legacy/signup.row_desired_username')}}</div>
            <div class="nx-fcell">
                {{ $usernameInput }}<br />
                <font class="small">{{ __('legacy/signup.text_allowed_characters')}}</font>
            </div>
            <div class="nx-fhead">{{ __('legacy/signup.row_pick_a_password') }}</div>
            <div class="nx-fcell">
                <input type="password" style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" class="wantpassword" aria-label="{{ __('legacy/signup.row_pick_a_password')}}" autocomplete="new-password" /><br />
                <font class="small">{{ __('legacy/signup.text_minimum_six_characters')}}</font>
            </div>
            <div class="nx-fhead">{{ __('legacy/signup.row_enter_password_again')}}</div>
            <div class="nx-fcell">
                <input type="password" style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" class="passagain" aria-label="{{ __('legacy/signup.row_enter_password_again')}}" autocomplete="new-password" />
            </div>

            @if ($captchaEnabled && $captchaMarkup !== '')
                {{ $captchaMarkup }}
            @endif

            <div class="nx-fhead">{{ __('legacy/signup.row_email_address')}}</div>
            <div class="nx-fcell">{{ $emailInput }}</div>

            <div class="nx-fhead">{{ __('legacy/signup.row_country')}}</div>
            <div class="nx-fcell">
                <select name="country" aria-label="{{ __('legacy/signup.row_country')}}" style="width: min(100%, 320px);">
                    <option value="8">---- {{ __('legacy/signup.select_none_selected')}} ----</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @if ((int) old('country', 8) === (int) $country->id) selected @endif>{{ $country->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="nx-fhead">{{ __('legacy/signup.row_gender')}}</div>
            <div class="nx-fcell">
                <input type="radio" name="gender" value="Male" aria-label="{{ __('legacy/signup.radio_male')}}" @if (old('gender') === 'Male') checked @endif />{{ __('legacy/signup.radio_male')}}
                <input type="radio" name="gender" value="Female" aria-label="{{ __('legacy/signup.radio_female')}}" @if (old('gender') === 'Female') checked @endif />{{ __('legacy/signup.radio_female')}}
            </div>

            <div class="nx-fhead">{{ __('legacy/signup.row_verification')}}</div>
            <div class="nx-fcell">
                <input type="checkbox" name="rulesverify" value="yes" aria-label="{{ ('I have read the site rules page')}}" @if (old('rulesverify') === 'yes') checked @endif />@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/signup.checkbox_read_rules')))<br />
                <input type="checkbox" name="faqverify" value="yes" aria-label="{{ ('I agree to read the FAQ before asking questions')}}" @if (old('faqverify') === 'yes') checked @endif />@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/signup.checkbox_read_faq')))<br />
                <input type="checkbox" name="ageverify" value="yes" aria-label="{{ __('legacy/signup.checkbox_age')}}" @if (old('ageverify') === 'yes') checked @endif />{{ __('legacy/signup.checkbox_age') }}
            </div>

            <div class="toolbox nx-ffull">
                <font color="#a00"><b>{{ __('legacy/signup.text_all_fields_required')}}</b></font><p></p>
                <input id="submit-btn" type="button" value="{{ __('legacy/signup.submit_sign_up')}}" style="height: 25px" />
            </div>
        </div>
        <input type="hidden" name="wantpassword" />
        <input type="hidden" name="passagain" />
    </form>
@endsection
