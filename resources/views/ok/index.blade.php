@extends('layouts.app', ['shell' => 'bare'])

@section('title', $vm->title)

@section('content')
@if ($vm->type == 'adminactivate')
    <x-std-message :heading="__('ok.std_account_activated')" :text="__('ok.account_activated_note')" :htmlstrip="false" />
@elseif ($vm->type == 'inviter')
    <x-std-message :heading="__('ok.std_account_activated')" :text="__('ok.account_activated_note_two')" :htmlstrip="false" />
@elseif ($vm->type == 'signup')
    <x-std-message :heading="__('ok.std_signup_successful')" :htmlstrip="false">{{ __('ok.std_confirmation_email_note') }}{{ $vm->email }}{{ __('ok.std_confirmation_email_note_end') }}</x-std-message>
@elseif ($vm->type == 'sysop')
    <x-std-message :heading="__('ok.std_sysop_activation_note')">
        @if (! empty($vm->CURUSER))
            <p>{{ __('ok.std_auto_logged_in_note') }} <a class=altlink href="/"><b>{{ __('ok.link_main_page') }}</b></a> {{ __('ok.std_auto_logged_in_note_end') }}</p>
        @else
            <p>{{ __('ok.std_cookies_disabled_note') }}<br />{{ __('ok.std_cookies_disabled_note_two') }} <a class=altlink href="/login">{{ __('ok.link_log_in') }}</a> {{ __('ok.std_cookies_disabled_note_end') }}</p>
        @endif
    </x-std-message>
@elseif ($vm->type == 'confirmed')
    <x-std-message :heading="__('ok.std_already_confirmed')">
        <p>{{ __('ok.std_already_confirmed_note') }} <a class=altlink href="/login">{{ __('ok.link_log_in') }}</a> {{ __('ok.std_already_confirmed_note_end') }}</p>
    </x-std-message>
@elseif ($vm->type == 'confirm')
    <x-std-message :heading="__('ok.std_account_confirmed')">
        @if (! empty($vm->CURUSER))
            <p>{{ __('ok.std_auto_logged_in_note') }} <a class=altlink href="/"><b>{{ __('ok.link_main_page') }}</b></a> {{ __('ok.std_auto_logged_in_note_end') }}</p>
            <p>{{ sprintf(__('ok.std_read_rules_faq'), $vm->siteName) }} <a class=altlink href="/web/rules"><b>{{ __('ok.text_rules') }}</b></a> {{ __('ok.std_read_rules_faq_and') }} <a class=altlink href="/web/faq"><b>{{ __('ok.text_faq') }}</b></a>.</p>
        @else
            <p>{{ __('ok.std_cookies_disabled_note') }}<br />{{ __('ok.std_cookies_disabled_note_two') }} <a class=altlink href="/login">{{ __('ok.link_log_in') }}</a> {{ __('ok.std_cookies_disabled_note_end') }}</p>
        @endif
    </x-std-message>
@endif
@endsection
