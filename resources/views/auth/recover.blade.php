@extends('layouts.app', ['chromeVariant' => 'auth'])

@section('title', __('recover.text_recover_user'))

@section('content')
    @if ($vm->error || $vm->resetError)
        <div class="nx-auth__error">{{ $vm->resetError ?? $vm->error }}</div>
    @endif

    <x-auth.errors :errors="$errors ?? null" />

    @if ($vm->status === 'requested')
        <div class="nx-auth__success">{{ __('recover.text_reset_link_sent') }}</div>
    @endif

    <form method="get" action="/recover" class="nx-auth__lang">
        @if ($vm->resetToken !== null)
            <input type="hidden" name="id" value="{{ $vm->resetUserId }}" />
            <input type="hidden" name="secret" value="{{ $vm->resetToken }}" />
        @endif
        <x-auth.language-select :label="__('recover.text_select_lang')" :languages="$vm->languages" :lang-folder="$vm->langFolder" />
    </form>

    @if ($vm->resetToken !== null)
        <h1>{{ __('recover.text_reset_password') }}</h1>
        <p class="nx-auth__sub">{{ __('recover.text_choose_new_password') }}</p>

        <form method="post" action="{{ route('recover.reset') }}">
            @csrf
            <input type="hidden" name="id" value="{{ $vm->resetUserId }}" />
            <input type="hidden" name="secret" value="{{ $vm->resetToken }}" />
            <x-form-field :label="__('recover.row_new_password')" name="password" type="password" autocomplete="new-password" :password-toggle="true" />
            <x-form-field :label="__('recover.row_confirm_password')" name="password_confirmation" type="password" autocomplete="new-password" />

            <div class="nx-auth__actions">
                <x-button type="submit" variant="primary" data-submit-once>{{ __('recover.submit_reset_password') }}</x-button>
            </div>
        </form>
    @else
        <h1>{{ __('recover.text_recover_user')}}</h1>
        <p class="nx-auth__sub">{{ __('recover.text_use_form_below')}} {{ __('recover.text_reply_to_confirmation_email')}}</p>

        <div class="nx-auth__info">
            <p><b>{{ __('recover.text_note')}}</b> {{ $vm->maxAttempts }} {{ __('recover.text_ban_ip')}}</p>
            <p>{{ __('recover.text_you_have')}} <b>{{ $vm->remaining }}</b> {{ __('recover.text_remaining_tries')}}</p>
        </div>

        <form method="post" action="/recover">
            @csrf
            <x-form-field :label="__('recover.row_registered_email')" name="email" type="email" :value="old('email')" autocomplete="email" />

            @if ($vm->captchaEnabled && $vm->captchaMarkup !== '')
                {{ $vm->captchaMarkup }}
            @endif

            <div class="nx-auth__actions">
                <x-button type="submit" variant="primary" data-submit-once>{{ __('recover.submit_recover_it')}}</x-button>
            </div>
        </form>

        <div class="nx-auth__links">
            <ul><li><a href="/login">{{ __('recover.text_back_to_login') }}</a></li></ul>
        </div>
    @endif
@endsection
