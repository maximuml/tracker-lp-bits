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

    <h1>{{ $headTitle }}</h1>
    <p class="nx-auth__sub"><b>{{ __('signup.text_note') }}</b>: {{ __('signup.text_cookies_note') }}</p>

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

        <p class="nx-auth__group nx-auth__group--first">{{ __('signup.group_account') }}</p>

        <div class="nx-field">
            <label class="nx-field__label" for="wantusername">{{ __('signup.row_desired_username')}}</label>
            <input type="text" class="nx-field__input" id="wantusername" name="wantusername" aria-label="{{ __('signup.row_desired_username') }}" value="{{ $usernameValue }}"@if($usernameReadonly) readonly @endif autocomplete="username"@error('wantusername') aria-invalid="true" aria-describedby="wantusername-error"@enderror />
            @error('wantusername')<div class="nx-field__error" id="wantusername-error" role="alert">{{ $message }}</div>@enderror
            <p class="nx-field__help">{{ __('signup.text_allowed_characters')}}</p>
        </div>
        <div class="nx-field">
            <label class="nx-field__label" for="signup-password">{{ __('signup.row_pick_a_password') }}</label>
            <div class="nx-pw">
                <input type="password" id="signup-password" class="nx-field__input wantpassword" autocomplete="new-password"@error('wantpassword') aria-invalid="true" aria-describedby="wantpassword-error"@enderror />
                <button type="button" class="nx-pw__toggle" data-pw-toggle
                        data-label-show="{{ __('functions.show_password') }}"
                        data-label-hide="{{ __('functions.hide_password') }}"
                        aria-label="{{ __('functions.show_password') }}" aria-pressed="false">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            </div>
            @error('wantpassword')<div class="nx-field__error" id="wantpassword-error" role="alert">{{ $message }}</div>@enderror
            <p class="nx-field__help">{{ __('signup.text_minimum_six_characters')}}</p>
        </div>
        <div class="nx-field">
            <label class="nx-field__label" for="signup-passagain">{{ __('signup.row_enter_password_again')}}</label>
            <input type="password" id="signup-passagain" class="nx-field__input passagain" autocomplete="new-password"@error('passagain') aria-invalid="true" aria-describedby="passagain-error"@enderror />
            @error('passagain')<div class="nx-field__error" id="passagain-error" role="alert">{{ $message }}</div>@enderror
        </div>

        @if ($captchaEnabled && $captchaMarkup !== '')
            {{ $captchaMarkup }}
            @error('imagestring')<div class="nx-field__error" id="imagestring-error" role="alert">{{ $message }}</div>@enderror
        @endif

        <div class="nx-field">
            <label class="nx-field__label" for="email">{{ __('signup.row_email_address')}}</label>
            <input type="email" class="nx-field__input" id="email" name="email" aria-label="{{ __('signup.row_email_address') }}" value="{{ $emailValue }}"@if($emailReadonly) readonly @endif autocomplete="email"@error('email') aria-invalid="true" aria-describedby="email-error"@enderror />
            @error('email')<div class="nx-field__error" id="email-error" role="alert">{{ $message }}</div>@enderror
        </div>

        <p class="nx-auth__group">{{ __('signup.group_about') }}</p>

        <div class="nx-field">
            <label class="nx-field__label" for="country">{{ __('signup.row_country')}}</label>
            <select id="country" name="country" class="nx-field__input"@error('country') aria-invalid="true" aria-describedby="country-error"@enderror>
                <option value="8">---- {{ __('signup.select_none_selected')}} ----</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->id }}" @if ((int) old('country', 8) === (int) $country->id) selected @endif>{{ $country->name }}</option>
                @endforeach
            </select>
            @error('country')<div class="nx-field__error" id="country-error" role="alert">{{ $message }}</div>@enderror
        </div>

        <div class="nx-field" role="group" aria-labelledby="signup-gender-label">
            <span class="nx-field__label" id="signup-gender-label">{{ __('signup.row_gender')}}</span>
            <div class="nx-auth__radios">
                <label class="nx-auth__checkline"><input type="radio" name="gender" value="Male" @checked(old('gender') === 'Male') />{{ __('signup.radio_male')}}</label>
                <label class="nx-auth__checkline"><input type="radio" name="gender" value="Female" @checked(old('gender') === 'Female') />{{ __('signup.radio_female')}}</label>
            </div>
            @error('gender')<div class="nx-field__error" id="gender-error" role="alert">{{ $message }}</div>@enderror
        </div>

        <div class="nx-field" role="group" aria-labelledby="signup-verification-label">
            <span class="nx-field__label" id="signup-verification-label">{{ __('signup.row_verification')}}</span>
            <span class="nx-auth__checkline">
                <label><input type="checkbox" name="rulesverify" value="yes" @checked(old('rulesverify') === 'yes')@error('rulesverify') aria-invalid="true" aria-describedby="rulesverify-error"@enderror />{{ __('signup.checkbox_read_rules') }}</label>
                <a href="/web/rules">{{ __('signup.text_rules') }}</a> {{ __('signup.checkbox_read_rules_end') }}
            </span>
            <span class="nx-auth__checkline">
                <label><input type="checkbox" name="faqverify" value="yes" @checked(old('faqverify') === 'yes')@error('faqverify') aria-invalid="true" aria-describedby="faqverify-error"@enderror />{{ __('signup.checkbox_read_faq') }}</label>
                <a href="/web/faq">{{ __('signup.text_faq') }}</a> {{ __('signup.checkbox_read_faq_end') }}
            </span>
            <label class="nx-auth__checkline"><input type="checkbox" name="ageverify" value="yes" @checked(old('ageverify') === 'yes')@error('ageverify') aria-invalid="true" aria-describedby="ageverify-error"@enderror />{{ __('signup.checkbox_age') }}</label>
            @error('rulesverify')<div class="nx-field__error" id="rulesverify-error" role="alert">{{ $message }}</div>@enderror
            @error('faqverify')<div class="nx-field__error" id="faqverify-error" role="alert">{{ $message }}</div>@enderror
            @error('ageverify')<div class="nx-field__error" id="ageverify-error" role="alert">{{ $message }}</div>@enderror
        </div>

        <p class="nx-auth__required">{{ __('signup.text_all_fields_required')}}</p>
        <div class="nx-auth__actions">
            <x-button type="submit" id="submit-btn" variant="primary" data-submit-once>{{ __('signup.submit_sign_up')}}</x-button>
        </div>
        <input type="hidden" name="wantpassword" />
        <input type="hidden" name="passagain" />
    </form>
@endsection
