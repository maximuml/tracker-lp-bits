@extends('layouts.legacy_torrents')

@section('title', $title ?? '')

@section('content')
@if ($type == 'adminactivate')
    {{ \App\Support\Frame::stdMessage(__('legacy/ok.std_account_activated'), __('legacy/ok.account_activated_note'), false) }}
@elseif ($type == 'inviter')
    {{ \App\Support\Frame::stdMessage(__('legacy/ok.std_account_activated'), __('legacy/ok.account_activated_note_two'), false) }}
@elseif ($type == 'signup')
    {{ \App\Support\Frame::stdMessage(__('legacy/ok.std_signup_successful'), (__('legacy/ok.std_confirmation_email_note')) . htmlspecialchars($email ?? '') . (__('legacy/ok.std_confirmation_email_note_end')), false) }}
@elseif ($type == 'sysop')
    <p><h1>{{ __('legacy/ok.std_sysop_activation_note') }}</h1></p>
    @if (! empty($CURUSER))
        <p>{{ __('legacy/ok.std_auto_logged_in_note') }} <a class=altlink href="/"><b>{{ __('legacy/ok.link_main_page') }}</b></a> {{ __('legacy/ok.std_auto_logged_in_note_end') }}</p>
    @else
        <p>{{ __('legacy/ok.std_cookies_disabled_note') }}<br />{{ __('legacy/ok.std_cookies_disabled_note_two') }} <a class=altlink href="login.php">{{ __('legacy/ok.link_log_in') }}</a> {{ __('legacy/ok.std_cookies_disabled_note_end') }}</p>
    @endif
@elseif ($type == 'confirmed')
    <p><h1>{{ __('legacy/ok.std_already_confirmed') }}</h1></p>
    <p>{{ __('legacy/ok.std_already_confirmed_note') }} <a class=altlink href="login.php">{{ __('legacy/ok.link_log_in') }}</a> {{ __('legacy/ok.std_already_confirmed_note_end') }}</p>
@elseif ($type == 'confirm')
    <p><h1>{{ __('legacy/ok.std_account_confirmed') }}</h1></p>
    @if (! empty($CURUSER))
        <p>{{ __('legacy/ok.std_auto_logged_in_note') }} <a class=altlink href="/"><b>{{ __('legacy/ok.link_main_page') }}</b></a> {{ __('legacy/ok.std_auto_logged_in_note_end') }}</p>
        <p>{{ sprintf(__('legacy/ok.std_read_rules_faq'), $siteName ?? '') }} <a class=altlink href="rules.php"><b>{{ __('legacy/ok.text_rules') }}</b></a> {{ __('legacy/ok.std_read_rules_faq_and') }} <a class=altlink href="faq.php"><b>{{ __('legacy/ok.text_faq') }}</b></a>.</p>
    @else
        <p>{{ __('legacy/ok.std_cookies_disabled_note') }}<br />{{ __('legacy/ok.std_cookies_disabled_note_two') }} <a class=altlink href="login.php">{{ __('legacy/ok.link_log_in') }}</a> {{ __('legacy/ok.std_cookies_disabled_note_end') }}</p>
    @endif
@endif
@endsection
