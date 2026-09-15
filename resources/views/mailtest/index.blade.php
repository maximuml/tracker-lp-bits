@extends('layouts.legacy_torrents')

@section('title', $lang_mailtest['head_mail_test'] ?? 'Mail test')

@section('content')
<h1 align="center">{{ $lang_mailtest['text_mail_test'] ?? 'Mail test' }}</h1>
<form method="post" action="mailtest.php">
        <input type="hidden" name="action" value="sendmail">
        <div class="nx-fgrid">
        <div class="nx-fhead nx-nowrap">{{ $lang_mailtest['row_enter_email'] ?? 'Enter email' }}</div><div class="nx-fcell"><input type='text' name='email' size=35><br />@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_mailtest['text_enter_email_note'] ?? ''))</div>
        <div class="nx-ffull nx-center"><input type="submit" name="sendmail" value="{{ $lang_mailtest['submit_send_it'] ?? 'Send it' }}"></div>
    </div>
</form>
@endsection
