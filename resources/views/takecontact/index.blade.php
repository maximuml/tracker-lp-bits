@extends('layouts.legacy')

@section('title', '')

@section('content')
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::stdMessage(__('legacy/takecontact.std_succeeded'), __('legacy/takecontact.std_message_succesfully_sent'), false)))

@endsection
