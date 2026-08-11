@extends('layouts.legacy')

@section('title', $lang_contactstaff['head_contact_staff'] ?? '')

@section('content')
@php
\App\Support\Frame::mainFrameOpen();
@endphp

<form id="compose" method="post" name="compose" action="takecontact.php">
    @php \App\Support\Frame::composeBeginVoid($lang_contactstaff['text_message_to_staff'], 'new'); @endphp
    @php \App\Support\Frame::composeEndVoid(); @endphp
</form>

@php
\App\Support\Frame::mainFrameClose();
@endphp
@endsection
