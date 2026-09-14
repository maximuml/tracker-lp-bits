@extends('layouts.legacy')

@section('title', $lang_contactstaff['head_contact_staff'] ?? '')

@section('content')
<form id="compose" method="post" name="compose" action="/takecontact">
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::composeBegin($lang_contactstaff['text_message_to_staff'] ?? '', 'new', '', true, '', 100)))
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::composeEnd()))
</form>
@endsection
