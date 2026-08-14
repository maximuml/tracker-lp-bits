@extends('layouts.legacy')

@section('title', $lang_rules['head_rules'] ?? '')

@section('content')
@if (! empty($rules))
    @foreach ($rules as $rule)
        @php \App\Support\Html::beginFrame($rule['title'], false); @endphp
        {!! \App\Support\Format::formatComment($rule['text']) !!}
        @php \App\Support\Html::endFrame(); @endphp
    @endforeach
@endif
@endsection
