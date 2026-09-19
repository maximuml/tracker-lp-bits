@extends('layouts.auth')

@section('title', __('legacy/confirm_resend.resend_confirmation_email_failed'))

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

    <form method="get" action="/confirm_resend" class="nx-auth__lang">
        <input type="hidden" name="secret" value="{{ $secret }}" />
        <label for="sitelanguage">{{ __('legacy/confirm_resend.text_select_lang')}}</label>
        <select id="sitelanguage" name="sitelanguage">
            @foreach ($languages as $row)
                <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                    {{ $row['lang_name'] }}
                </option>
            @endforeach
        </select>
    </form>

    <h1>{{ __('legacy/confirm_resend.text_resend_confirmation_mail_note') }} </h1><p>{{ __('legacy/confirm_resend.text_resend_confirmation_mail_note_two') }}</p><p>{{ __('legacy/confirm_resend.text_resend_confirmation_mail_note_three') }}<br />{{ __('legacy/confirm_resend.text_resend_confirmation_mail_note_four') }}</p><p><b>{{ __('legacy/confirm_resend.text_note') }}</b> {{ sprintf(__('legacy/confirm_resend.text_resend_confirmation_mail_attempts'), $maxAttempts) }}</p>

    <p>{{ __('legacy/confirm_resend.text_you_have')}} <b>{{ $remaining }}</b> {{ __('legacy/confirm_resend.text_remaining_tries')}}</p>

    <form method="post" action="/confirm_resend">
        @csrf
        <input type="hidden" name="secret" value="{{ $secret }}" />
        <x-form-field :label="__('legacy/confirm_resend.row_registered_email')" name="email" type="email" :value="old('email')" autocomplete="email" />
        <x-form-field :label="__('legacy/confirm_resend.row_new_password')" name="wantpassword" type="password" autocomplete="new-password" :help="__('legacy/confirm_resend.text_password_note')" />
        <x-form-field :label="__('legacy/confirm_resend.row_enter_password_again')" name="passagain" type="password" autocomplete="new-password" />

        @if ($captchaEnabled && $captchaMarkup !== '')
            {{ $captchaMarkup }}
        @endif

        <div class="nx-auth__actions">
            <x-button type="submit" variant="primary">{{ __('legacy/confirm_resend.submit_send_it')}}</x-button>
        </div>
    </form>
@endsection
