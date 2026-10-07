@extends('layouts.app', ['chromeVariant' => 'legacy', 'shell' => 'bare'])

@section('title', $title ?? '')

@section('content')
@if ($type == 'adminactivate')
    <x-std-message :heading="__('ok.std_account_activated')" :text="__('ok.account_activated_note')" :htmlstrip="false" />
@elseif ($type == 'inviter')
    <x-std-message :heading="__('ok.std_account_activated')" :text="__('ok.account_activated_note_two')" :htmlstrip="false" />
@elseif ($type == 'signup')
    <x-std-message :heading="__('ok.std_signup_successful')" :htmlstrip="false">{{ __('ok.std_confirmation_email_note') }}{{ $email ?? '' }}{{ __('ok.std_confirmation_email_note_end') }}</x-std-message>
@elseif ($type == 'sysop')
    <p><h1>{{ __('ok.std_sysop_activation_note') }}</h1></p>
    @if (! empty($CURUSER))
        <p>{{ __('ok.std_auto_logged_in_note') }} <a class=altlink href="/"><b>{{ __('ok.link_main_page') }}</b></a> {{ __('ok.std_auto_logged_in_note_end') }}</p>
    @else
        <p>{{ __('ok.std_cookies_disabled_note') }}<br />{{ __('ok.std_cookies_disabled_note_two') }} <a class=altlink href="/login">{{ __('ok.link_log_in') }}</a> {{ __('ok.std_cookies_disabled_note_end') }}</p>
    @endif
@elseif ($type == 'confirmed')
    <p><h1>{{ __('ok.std_already_confirmed') }}</h1></p>
    <p>{{ __('ok.std_already_confirmed_note') }} <a class=altlink href="/login">{{ __('ok.link_log_in') }}</a> {{ __('ok.std_already_confirmed_note_end') }}</p>
@elseif ($type == 'confirm')
    <p><h1>{{ __('ok.std_account_confirmed') }}</h1></p>
    @if (! empty($CURUSER))
        <p>{{ __('ok.std_auto_logged_in_note') }} <a class=altlink href="/"><b>{{ __('ok.link_main_page') }}</b></a> {{ __('ok.std_auto_logged_in_note_end') }}</p>
        <p>{{ sprintf(__('ok.std_read_rules_faq'), $siteName ?? '') }} <a class=altlink href="/web/rules"><b>{{ __('ok.text_rules') }}</b></a> {{ __('ok.std_read_rules_faq_and') }} <a class=altlink href="/web/faq"><b>{{ __('ok.text_faq') }}</b></a>.</p>
    @else
        <p>{{ __('ok.std_cookies_disabled_note') }}<br />{{ __('ok.std_cookies_disabled_note_two') }} <a class=altlink href="/login">{{ __('ok.link_log_in') }}</a> {{ __('ok.std_cookies_disabled_note_end') }}</p>
    @endif
@endif
@endsection
