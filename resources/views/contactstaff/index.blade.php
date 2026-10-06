@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('legacy/contactstaff.head_contact_staff'))

@section('content')
<form id="compose" method="post" name="compose" action="/web/contactstaff/send">
    <x-compose :title="__('legacy/contactstaff.text_message_to_staff')" type="new" :has-subject="true" />
</form>
@endsection
