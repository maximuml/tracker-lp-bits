@extends('layouts.app')

@section('title', __('rules.head_rules'))

@section('content')
<h1 class="nx-sr-only">{{ __('rules.head_rules') }}</h1>
@if (! empty($rules))
    @foreach ($rules as $rule)
        <h2 class="text-left">{{ $rule['heading'] }}@if ($rule['emphasis'] !== '') - <span class="striking">{{ $rule['emphasis'] }}</span>@endif</h2>
        <div class="nx-box">
        {{ \App\Support\Format::formatComment($rule['text']) }}
        </div>
    @endforeach
@endif
@endsection
