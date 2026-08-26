@php
$lang_sendmessage = (array) (\App\Support\SupportContext::getGlobal('lang_sendmessage') ?? []);
$CURUSER = (array) (\app(\App\Support\CurrentUser::class)->get() ?? []);
$receiver = (int) ($receiver ?? 0);
$replyto = (int) ($replyto ?? 0);
$subject = (string) ($subject ?? '');
$body = (string) ($body ?? '');
$returnto = (string) ($returnto ?? '');
$title = (string) ($title ?? ($lang_sendmessage['head_send_message'] ?? 'Send message'));
$deleteChecked = ($CURUSER['deletepms'] ?? '') == 'yes' ? ' checked' : '';
$saveChecked = ($CURUSER['savepms'] ?? '') == 'yes' ? ' checked' : '';
$stdheadMsgalert = false;
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
<form id="compose" name="compose" method="post" action="takemessage.php">
@csrf
<input type="hidden" name="receiver" value="{{ $receiver }}">
@if ($returnto !== '')
    <input type="hidden" name="returnto" value="{{ $returnto }}">
@endif
{!! \App\Support\Frame::composeBegin($title, $replyto ? 'reply' : 'new', $body, true, $subject, 100) !!}
<tr><td class="toolbox" colspan="2" align="center">
@if ($replyto)
    <input type="checkbox" name="delete" value="yes"{{ $deleteChecked }}> {{ $lang_sendmessage['checkbox_delete_message_replying_to'] ?? 'Delete message replying to' }}
    <input type="hidden" name="origmsg" value="{{ $replyto }}">
@endif
    <input type="checkbox" name="save" value="yes"{{ $saveChecked }}> {{ $lang_sendmessage['checkbox_save_message_to_sendbox'] ?? 'Save message to sendbox' }}
</td></tr>
{!! \App\Support\Frame::composeEnd() !!}
</form>
@endsection
