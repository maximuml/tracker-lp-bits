@extends('layouts.legacy_torrents')

@section('title', __('legacy/mailtest.head_mail_test'))

@section('content')
<h1 align="center">{{ __('legacy/mailtest.text_mail_test')}}</h1>
<form method="post" action="mailtest.php">
        <input type="hidden" name="action" value="sendmail">
        <div class="nx-fgrid">
        <div class="nx-fhead nx-nowrap">{{ __('legacy/mailtest.row_enter_email')}}</div><div class="nx-fcell"><input type='text' name='email' size=35><br />@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/mailtest.text_enter_email_note')))</div>
        <div class="nx-ffull nx-center"><input type="submit" name="sendmail" value="{{ __('legacy/mailtest.submit_send_it')}}"></div>
    </div>
</form>
@endsection
