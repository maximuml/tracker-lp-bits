
@extends('layouts.legacy')

@section('title', match ($mode ?? '') {
    'list' => $lang_staffbox['head_staff_pm'] ?? 'Staff PM',
    'viewpm' => $lang_staffbox['head_view_staff_pm'] ?? 'View staff PM',
    'answermessage' => $lang_staffbox['head_answer_to_staff_pm'] ?? 'Answer to staff PM',
    default => $lang_staffbox['head_staff_pm'] ?? 'Staff PM',
})

@section('content')
@include('staffbox._staffbox')
@endsection
