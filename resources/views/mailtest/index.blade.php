@extends('layouts.app', ['chromeVariant' => 'legacy', 'shell' => 'bare'])

@section('title', __('mailtest.head_mail_test'))

@section('content')
<h1 class="text-center">{{ __('mailtest.text_mail_test')}}</h1>
<form method="post" action="/web/system/mail-test">@csrf
        <input type="hidden" name="action" value="sendmail">
        <div class="nx-fgrid">
        <div class="nx-fhead whitespace-nowrap">{{ __('mailtest.row_enter_email')}}</div><div class="nx-fcell"><input type='text' name='email' size=35><br />{{ __('mailtest.text_enter_email_note') }}</div>
        <div class="nx-ffull text-center"><input type="submit" name="sendmail" value="{{ __('mailtest.submit_send_it')}}"></div>
    </div>
</form>
@endsection
