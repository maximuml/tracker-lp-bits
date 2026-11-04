@extends('layouts.app')

@section('title', __('rules.head_rules'))

@section('content')
<h1 class="nx-sr-only">{{ __('rules.head_rules') }}</h1>
@if (! empty($rules))
    @foreach ($rules as $rule)
        <x-frame :caption="$rule['title']" :center="false">
        {{ \App\Support\Format::formatComment($rule['text']) }}
        </x-frame>
    @endforeach
@endif
@endsection
