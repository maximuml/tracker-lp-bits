@extends('layouts.legacy_torrents')

@section('title', $lang_mailtest['head_mail_test'] ?? 'Mail test')

@section('content')
@php
if (\App\Support\UserDisplay::currentClass() < UC_SYSOP) {
    \App\Support\LegacyResponse::permissionDenied();
}

$action = (\App\Support\SupportContext::getPost('action') !== null)
    ? htmlspecialchars(\App\Support\SupportContext::getPost('action'))
    : '';

if ($action == 'sendmail') {
    $email = htmlspecialchars(trim(\App\Support\SupportContext::getPost('email')));
    $email = safe_email($email);
    if (! check_email($email)) {
        \App\Support\LegacyResponse::abort($lang_mailtest['std_error'], $lang_mailtest['std_invalid_email_address']);
    }
    $title = $SITENAME . $lang_mailtest['text_smtp_testing_mail'];
    $body = $lang_mailtest['mail_test_mail_content'];
    $sendResult = sent_mail($email, $SITENAME, $SITEEMAIL, $title, $body, 'mailtest', false, false, '', 'UTF-8');
    if ($sendResult === true) {
        \App\Support\LegacyResponse::abort($lang_mailtest['std_success'], $lang_mailtest['std_success_note']);
    } else {
        \App\Support\LegacyResponse::abort($lang_functions['std_error'], $lang_functions['text_unable_to_send_mail'] . ' (SMTP disabled or mail not sent)', false);
    }
}
@endphp

<h1 align="center">{{ $lang_mailtest['text_mail_test'] }}</h1>
<table border="1" cellspacing="0" cellpadding="5">
    <form method="post" action="mailtest.php">
        <input type="hidden" name="action" value="sendmail">
        @php
            $row = \App\Support\Html::tr(
                $lang_mailtest['row_enter_email'],
                "<input type='text' name='email' size=35><br />" . $lang_mailtest['text_enter_email_note'],
                1
            );
        @endphp
        {!! $row !!}
        <tr><td colspan="2" align="center"><input type="submit" name="sendmail" value="{{ $lang_mailtest['submit_send_it'] }}"></td></tr>
    </form>
</table>
@endsection
