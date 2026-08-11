@php
$type = \App\Support\SupportContext::getRequestInput('type');
if ($type === null) {
    return;
}
$email = null;
if ($type == 'signup') {
    $email = \App\Support\SupportContext::getRequestInput('email');
    if ($email === null) {
        return;
    }
}
$title = match ($type) {
    'adminactivate', 'inviter', 'signup' => $lang_ok['head_user_signup'] ?? '',
    'sysop' => $lang_ok['head_sysop_activation'] ?? '',
    'confirmed' => $lang_ok['head_already_confirmed'] ?? '',
    'confirm' => $lang_ok['head_signup_confirmation'] ?? '',
    default => '',
};
@endphp

@extends('layouts.legacy_torrents')

@section('title', $title ?? '')

@section('content')
@if ($type == 'adminactivate')
    @php \App\Support\Html::stdMessage($lang_ok['std_account_activated'], $lang_ok['account_activated_note']); @endphp
@elseif ($type == 'inviter')
    @php \App\Support\Html::stdMessage($lang_ok['std_account_activated'], $lang_ok['account_activated_note_two']); @endphp
@elseif ($type == 'signup')
    @php \App\Support\Html::stdMessage($lang_ok['std_signup_successful'], $lang_ok['std_confirmation_email_note'] . htmlspecialchars($email) . $lang_ok['std_confirmation_email_note_end']); @endphp
@elseif ($type == 'sysop')
    <p>{{ $lang_ok['std_sysop_activation_note'] }}</p>
    @if (isset($CURUSER))
        <p>{{ $lang_ok['std_auto_logged_in_note'] }}</p>
    @else
        <p>{{ $lang_ok['std_cookies_disabled_note'] }}</p>
    @endif
@elseif ($type == 'confirmed')
    <p>{{ $lang_ok['std_already_confirmed'] }}</p>
    <p>{{ $lang_ok['std_already_confirmed_note'] }}</p>
@elseif ($type == 'confirm')
    @if (isset($CURUSER))
        <p>{{ $lang_ok['std_account_confirmed'] }}</p>
        <p>{{ $lang_ok['std_auto_logged_in_note'] }}</p>
        <p>{!! sprintf($lang_ok['std_read_rules_faq'], \App\Models\Setting::getSiteName()) !!}</p>
    @else
        <p>{{ $lang_ok['std_account_confirmed'] }}</p>
        <p>{{ $lang_ok['std_cookies_disabled_note'] }}</p>
    @endif
@endif
@endsection
