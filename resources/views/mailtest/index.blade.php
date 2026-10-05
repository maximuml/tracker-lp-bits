@extends('layouts.app', ['chromeVariant' => 'legacy', 'shell' => 'bare'])

@section('title', __('legacy/mailtest.head_mail_test'))

@section('content')
<h1 class="text-center">{{ __('legacy/mailtest.text_mail_test')}}</h1>
<form method="post" action="mailtest.php">
        <input type="hidden" name="action" value="sendmail">
        <div class="nx-fgrid">
        <div class="nx-fhead whitespace-nowrap">{{ __('legacy/mailtest.row_enter_email')}}</div><div class="nx-fcell"><input type='text' name='email' size=35><br />{{ __('legacy/mailtest.text_enter_email_note') }}</div>
        <div class="nx-ffull text-center"><input type="submit" name="sendmail" value="{{ __('legacy/mailtest.submit_send_it')}}"></div>
    </div>
</form>
@endsection
