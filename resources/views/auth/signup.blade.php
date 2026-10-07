@extends('layouts.app', ['chromeVariant' => 'auth'])

@section('title', $headTitle)

@section('content')
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

    <form method="get" action="/signup" class="nx-auth__lang">
        @if ($isInvite)
            <input type="hidden" name="type" value="invite" />
            <input type="hidden" name="invitenumber" value="{{ $code }}" />
        @endif
        <input type="hidden" name="secret" value="{{ $secret }}" />
        <label for="sitelanguage">{{ __('signup.text_select_lang')}}</label>
        <select id="sitelanguage" name="sitelanguage">
            @foreach ($languages as $row)
                <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                    {{ $row['lang_name'] }}
                </option>
            @endforeach
        </select>
    </form>

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

        <p class="nx-auth__note"><b>{{ __('signup.text_note') }}</b>: {{ __('signup.text_cookies_note') }}</p>

        <div class="nx-field">
            <label class="nx-field__label" for="wantusername">{{ __('signup.row_desired_username')}}</label>
            <input type="text" class="nx-field__input" id="wantusername" name="wantusername" aria-label="{{ __('signup.row_desired_username') }}" value="{{ $usernameValue }}"@if($usernameReadonly) readonly @endif autocomplete="username" />
            <p class="nx-field__help">{{ __('signup.text_allowed_characters')}}</p>
        </div>
        <div class="nx-field">
            <label class="nx-field__label" for="signup-password">{{ __('signup.row_pick_a_password') }}</label>
            <input type="password" id="signup-password" class="nx-field__input wantpassword" autocomplete="new-password" />
            <p class="nx-field__help">{{ __('signup.text_minimum_six_characters')}}</p>
        </div>
        <div class="nx-field">
            <label class="nx-field__label" for="signup-passagain">{{ __('signup.row_enter_password_again')}}</label>
            <input type="password" id="signup-passagain" class="nx-field__input passagain" autocomplete="new-password" />
        </div>

        @if ($captchaEnabled && $captchaMarkup !== '')
            {{ $captchaMarkup }}
        @endif

        <div class="nx-field">
            <label class="nx-field__label" for="email">{{ __('signup.row_email_address')}}</label>
            <input type="email" class="nx-field__input" id="email" name="email" aria-label="{{ __('signup.row_email_address') }}" value="{{ $emailValue }}"@if($emailReadonly) readonly @endif autocomplete="email" />
        </div>

        <div class="nx-field">
            <label class="nx-field__label" for="country">{{ __('signup.row_country')}}</label>
            <select id="country" name="country" class="nx-field__input">
                <option value="8">---- {{ __('signup.select_none_selected')}} ----</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->id }}" @if ((int) old('country', 8) === (int) $country->id) selected @endif>{{ $country->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="nx-field">
            <span class="nx-field__label">{{ __('signup.row_gender')}}</span>
            <label class="nx-auth__checkline"><input type="radio" name="gender" value="Male" @checked(old('gender') === 'Male') />{{ __('signup.radio_male')}}</label>
            <label class="nx-auth__checkline"><input type="radio" name="gender" value="Female" @checked(old('gender') === 'Female') />{{ __('signup.radio_female')}}</label>
        </div>

        <div class="nx-field">
            <span class="nx-field__label">{{ __('signup.row_verification')}}</span>
            <span class="nx-auth__checkline">
                <label><input type="checkbox" name="rulesverify" value="yes" @checked(old('rulesverify') === 'yes') />{{ __('signup.checkbox_read_rules') }}</label>
                <a href="/web/rules">{{ __('signup.text_rules') }}</a> {{ __('signup.checkbox_read_rules_end') }}
            </span>
            <span class="nx-auth__checkline">
                <label><input type="checkbox" name="faqverify" value="yes" @checked(old('faqverify') === 'yes') />{{ __('signup.checkbox_read_faq') }}</label>
                <a href="/web/faq">{{ __('signup.text_faq') }}</a> {{ __('signup.checkbox_read_faq_end') }}
            </span>
            <label class="nx-auth__checkline"><input type="checkbox" name="ageverify" value="yes" @checked(old('ageverify') === 'yes') />{{ __('signup.checkbox_age') }}</label>
        </div>

        <p class="nx-auth__required">{{ __('signup.text_all_fields_required')}}</p>
        <div class="nx-auth__actions">
            <x-button type="button" id="submit-btn" variant="primary">{{ __('signup.submit_sign_up')}}</x-button>
        </div>
        <input type="hidden" name="wantpassword" />
        <input type="hidden" name="passagain" />
    </form>
@endsection
