@extends('layouts.legacy')

@section('title', __('legacy/contactstaff.head_contact_staff'))

@section('content')
<form id="compose" method="post" name="compose" action="/takecontact">
    {{ \App\Support\Frame::composeBegin(__('legacy/contactstaff.text_message_to_staff'), 'new', '', true, '', 100) }}
    {{ \App\Support\Frame::composeEnd() }}
</form>
@endsection
