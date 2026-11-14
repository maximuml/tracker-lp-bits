@extends('layouts.app', ['chromeVariant' => 'auth'])

@section('title', __('confirm_resend.text_resend_confirmation_mail_note'))

@section('content')
    @if ($vm->error)
        <div class="nx-auth__error">{{ $vm->error }}</div>
    @endif

    <x-auth.errors :errors="$errors ?? null" />

    <form method="get" action="/confirm_resend" class="nx-auth__lang">
        <input type="hidden" name="secret" value="{{ $vm->secret }}" />
        <x-auth.language-select :label="__('confirm_resend.text_select_lang')" :languages="$vm->languages" :lang-folder="$vm->langFolder" />
    </form>

    <h1>{{ __('confirm_resend.text_resend_confirmation_mail_note') }} </h1><p>{{ __('confirm_resend.text_resend_confirmation_mail_note_two') }}</p><p>{{ __('confirm_resend.text_resend_confirmation_mail_note_three') }}<br />{{ __('confirm_resend.text_resend_confirmation_mail_note_four') }}</p>

    <div class="nx-auth__info">
        <p><b>{{ __('confirm_resend.text_note') }}</b> {{ sprintf(__('confirm_resend.text_resend_confirmation_mail_attempts'), $vm->maxAttempts) }}</p>
        <p>{{ __('confirm_resend.text_you_have')}} <b>{{ $vm->remaining }}</b> {{ __('confirm_resend.text_remaining_tries')}}</p>
    </div>

    <form method="post" action="/confirm_resend">
        @csrf
        <input type="hidden" name="secret" value="{{ $vm->secret }}" />
        <x-form-field :label="__('confirm_resend.row_registered_email')" name="email" type="email" :value="old('email')" autocomplete="email" />

        @if ($vm->captchaEnabled && $vm->captchaMarkup !== '')
            {{ $vm->captchaMarkup }}
        @endif

        <div class="nx-auth__actions">
            <x-button type="submit" variant="primary">{{ __('confirm_resend.submit_send_it')}}</x-button>
        </div>
    </form>

    <div class="nx-auth__links">
        <ul><li><a href="/login">{{ __('recover.text_back_to_login') }}</a></li></ul>
    </div>
@endsection
