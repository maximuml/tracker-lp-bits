@extends('layouts.legacy')

@section('title', __('legacy/rules.head_rules'))

@section('content')
@if (! empty($rules))
    @foreach ($rules as $rule)
        {{ \App\Support\Frame::open((string) $rule['title'], false, 10, '100%', 'left') }}
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Format::formatComment($rule['text'])))
        {{ \App\Support\Frame::close() }}
    @endforeach
@endif
@endsection
