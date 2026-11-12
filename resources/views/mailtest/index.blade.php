@extends('layouts.app')

@section('title', __('mailtest.head_mail_test'))

@section('content')
<x-page :title="__('mailtest.text_mail_test')" width="form">
<form method="post" action="/web/system/mail-test">@csrf
        <input type="hidden" name="action" value="sendmail">
        <div class="nx-fgrid">
        <label class="nx-fhead" for="mailtest-email">{{ __('mailtest.row_enter_email')}}</label><div class="nx-fcell"><input type='text' id="mailtest-email" name='email' size=35><br />{{ __('mailtest.text_enter_email_note') }}</div>
        <div class="nx-ffull text-center"><input type="submit" name="sendmail" class="nx-btn nx-btn--primary" value="{{ __('mailtest.submit_send_it')}" data-submit-once></div>
    </div>
</form>
</x-page>
@endsection
