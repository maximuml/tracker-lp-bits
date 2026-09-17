@extends('layouts.legacy')

@section('title', '')

@section('content')
{{ \App\Support\Frame::stdMessage(__('legacy/takecontact.std_succeeded'), __('legacy/takecontact.std_message_succesfully_sent'), false) }}

@endsection
