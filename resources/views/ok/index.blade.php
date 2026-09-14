@extends('layouts.legacy_torrents')

@section('title', $title ?? '')

@section('content')
@if ($type == 'adminactivate')
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::stdMessage($lang_ok['std_account_activated'] ?? '', $lang_ok['account_activated_note'] ?? '', false)))
@elseif ($type == 'inviter')
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::stdMessage($lang_ok['std_account_activated'] ?? '', $lang_ok['account_activated_note_two'] ?? '', false)))
@elseif ($type == 'signup')
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::stdMessage($lang_ok['std_signup_successful'] ?? '', ($lang_ok['std_confirmation_email_note'] ?? '') . htmlspecialchars($email ?? '') . ($lang_ok['std_confirmation_email_note_end'] ?? ''), false)))
@elseif ($type == 'sysop')
    <p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_ok['std_sysop_activation_note'] ?? ''))</p>
    @if (! empty($CURUSER))
        <p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_ok['std_auto_logged_in_note'] ?? ''))</p>
    @else
        <p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_ok['std_cookies_disabled_note'] ?? ''))</p>
    @endif
@elseif ($type == 'confirmed')
    <p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_ok['std_already_confirmed'] ?? ''))</p>
    <p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_ok['std_already_confirmed_note'] ?? ''))</p>
@elseif ($type == 'confirm')
    <p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_ok['std_account_confirmed'] ?? ''))</p>
    @if (! empty($CURUSER))
        <p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_ok['std_auto_logged_in_note'] ?? ''))</p>
        <p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(sprintf($lang_ok['std_read_rules_faq'] ?? '%s', $siteName ?? '')))</p>
    @else
        <p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_ok['std_cookies_disabled_note'] ?? ''))</p>
    @endif
@endif
@endsection
