@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('rules.head_rules'))

@section('content')
@if (! empty($rules))
    @foreach ($rules as $rule)
        <x-frame :caption="$rule['title']" :center="false">
        {{ \App\Support\Format::formatComment($rule['text']) }}
        </x-frame>
    @endforeach
@endif
@endsection
