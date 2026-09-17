@extends('layouts.legacy')

@section('title', $title ?? (__('legacy/sendmessage.head_send_message')))

@section('content')
<form id="compose" name="compose" method="post" action="/takemessage">
@csrf
<input type="hidden" name="receiver" value="{{ $receiver }}">
@if ($returnto !== '')
    <input type="hidden" name="returnto" value="{{ $returnto }}">
@endif
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::composeBegin($frameTitle ?? $title, $replyto ? 'reply' : 'new', $body, true, $subject, 100)))
<tr><td class="toolbox" colspan="2" align="center">
@if ($replyto)
    <input type="checkbox" name="delete" value="yes"{{ $deleteChecked }}> {{ __('legacy/sendmessage.checkbox_delete_message_replying_to')}}
    <input type="hidden" name="origmsg" value="{{ $replyto }}">
@endif
    <input type="checkbox" name="save" value="yes"{{ $saveChecked }}> {{ __('legacy/sendmessage.checkbox_save_message_to_sendbox')}}
</td></tr>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::composeEnd()))
</form>
@endsection
