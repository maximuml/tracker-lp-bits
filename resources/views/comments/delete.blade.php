@extends('layouts.legacy')

@section('title', $heading)

@section('content')
{{ \App\Support\Frame::stdMessage($heading, $message, false) }}
<form method="post" action="{{ $formAction }}">
    @csrf
    <input type="hidden" name="type" value="{{ $type ?? '' }}">
    @if (! empty($returnto))
        <input type="hidden" name="returnto" value="{{ $returnto }}">
    @endif
    <p align="center">
        <button type="submit">{{ $confirmLabel }}</button>
        @if (($cancelLabel ?? '') !== '')
            &nbsp;|&nbsp;
            <a href="{{ $cancelUrl }}">{{ $cancelLabel }}</a>
        @endif
    </p>
</form>
@endsection
