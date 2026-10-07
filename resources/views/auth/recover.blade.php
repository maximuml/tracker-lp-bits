@extends('layouts.app', ['chromeVariant' => 'auth'])

@section('title', __('recover.text_recover_user'))

@section('content')
    @if ($error || $resetError)
        <div class="nx-auth__error">{{ $resetError ?? $error }}</div>
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
        @if ($resetToken !== null)
            <input type="hidden" name="id" value="{{ $resetUserId }}" />
            <input type="hidden" name="secret" value="{{ $resetToken }}" />
        @endif
        <label for="sitelanguage">{{ __('recover.text_select_lang')}}</label>
        <select id="sitelanguage" name="sitelanguage">
            @foreach ($languages as $row)
                <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                    {{ $row['lang_name'] }}
                </option>
            @endforeach
        </select>
    </form>

    @if ($resetToken !== null)
        <h1>{{ __('recover.text_reset_password') }}</h1>
        <p>{{ __('recover.text_choose_new_password') }}</p>

        <form method="post" action="{{ route('recover.reset') }}">
            @csrf
            <input type="hidden" name="id" value="{{ $resetUserId }}" />
            <input type="hidden" name="secret" value="{{ $resetToken }}" />
            <x-form-field :label="__('recover.row_new_password')" name="password" type="password" autocomplete="new-password" />
            <x-form-field :label="__('recover.row_confirm_password')" name="password_confirmation" type="password" autocomplete="new-password" />

            <div class="nx-auth__actions">
                <x-button type="submit" variant="primary">{{ __('recover.submit_reset_password') }}</x-button>
            </div>
        </form>
    @else
        <h1>{{ __('recover.text_recover_user')}}</h1>
        <p>{{ __('recover.text_use_form_below')}}</p>
        <p>{{ __('recover.text_reply_to_confirmation_email')}}</p>
        <p><b>{{ __('recover.text_note')}}</b> {{ $maxAttempts }} {{ __('recover.text_ban_ip')}}</p>
        <p>{{ __('recover.text_you_have')}} <b>{{ $remaining }}</b> {{ __('recover.text_remaining_tries')}}</p>

        <form method="post" action="/recover">
            @csrf
            <x-form-field :label="__('recover.row_registered_email')" name="email" type="email" :value="old('email')" autocomplete="email" />

            @if ($captchaEnabled && $captchaMarkup !== '')
                {{ $captchaMarkup }}
            @endif

            <div class="nx-auth__actions">
                <x-button type="submit" variant="primary">{{ __('recover.submit_recover_it')}}</x-button>
            </div>
        </form>
    @endif
@endsection
