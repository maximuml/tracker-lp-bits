@extends('layouts.legacy')

@section('title', '')

@section('content')
@php
\App\Support\Html::stdMessage($lang_takecontact['std_succeeded'] ?? 'Succeeded', $lang_takecontact['std_message_succesfully_sent'] ?? 'Message successfully sent.');
@endphp

@endsection
