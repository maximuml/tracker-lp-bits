@extends('layouts.legacy')

@section('title', '')

@section('content')
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::stdMessage($lang_takecontact['std_succeeded'] ?? 'Succeeded', $lang_takecontact['std_message_succesfully_sent'] ?? 'Message successfully sent.', false)))

@endsection
