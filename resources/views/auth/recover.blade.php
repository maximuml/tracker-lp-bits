@extends('layouts.auth')

@section('title', __('legacy/recover.text_recover_user'))

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

    @if ($status === 'requested')
        <div class="nx-auth__success">If an account with that email exists, a reset link has been sent.</div>
    @endif

    <form method="get" action="/recover" class="nx-auth__lang">
        <input type="hidden" name="secret" value="{{ $secret }}" />
        <label for="sitelanguage">{{ __('legacy/recover.text_select_lang')}}</label>
        <select id="sitelanguage" name="sitelanguage">
            @foreach ($languages as $row)
                <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                    {{ $row['lang_name'] }}
                </option>
            @endforeach
        </select>
    </form>

    <h1>{{ __('legacy/recover.text_recover_user')}}</h1>
    <p>{{ __('legacy/recover.text_use_form_below')}}</p>
    <p>{{ __('legacy/recover.text_reply_to_confirmation_email')}}</p>
    <p><b>{{ __('legacy/recover.text_note')}}</b> {{ $maxAttempts }} {{ __('legacy/recover.text_ban_ip')}}</p>
    <p>{{ __('legacy/recover.text_you_have')}} <b>{{ $remaining }}</b> {{ __('legacy/recover.text_remaining_tries')}}</p>

    <form method="post" action="/recover">
        @csrf
        <input type="hidden" name="secret" value="{{ $secret }}" />
        <x-form-field :label="__('legacy/recover.row_registered_email')" name="email" type="email" :value="old('email')" autocomplete="email" />

        @if ($captchaEnabled && $captchaMarkup !== '')
            {{ $captchaMarkup }}
        @endif

        <div class="nx-auth__actions">
            <x-button type="submit" variant="primary">{{ __('legacy/recover.submit_recover_it')}}</x-button>
        </div>
    </form>
@endsection
