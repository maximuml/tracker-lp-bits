@extends('layouts.legacy_torrents')

@section('title', $lang_mailtest['head_mail_test'] ?? 'Mail test')

@section('content')
<h1 align="center">{{ $lang_mailtest['text_mail_test'] ?? 'Mail test' }}</h1>
<table border="1" cellspacing="0" cellpadding="5">
    <form method="post" action="mailtest.php">
        <input type="hidden" name="action" value="sendmail">
        @php
            $row = \App\Support\Html::tr(
                $lang_mailtest['row_enter_email'] ?? 'Enter email',
                "<input type='text' name='email' size=35><br />" . ($lang_mailtest['text_enter_email_note'] ?? ''),
                1
            );
        @endphp
        {!! $row !!}
        <tr><td colspan="2" align="center"><input type="submit" name="sendmail" value="{{ $lang_mailtest['submit_send_it'] ?? 'Send it' }}"></td></tr>
    </form>
</table>
@endsection
