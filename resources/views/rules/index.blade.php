@extends('layouts.app')

@section('title', __('rules.head_rules'))

@section('content')
<x-page :title="__('rules.head_rules')" width="list">
@if (! empty($rules))
    @foreach ($rules as $rule)
        <h2 class="text-left">{{ $rule['heading'] }}@if ($rule['emphasis'] !== '') - <span class="striking">{{ $rule['emphasis'] }}</span>@endif</h2>
        <div class="nx-box">
        {{ \App\Support\Format::formatComment($rule['text']) }}
        </div>
    @endforeach
@endif
</x-page>
@endsection
