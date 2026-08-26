@php
$lang_fields = (array) ($lang_fields ?? \app(\App\Support\Globals::class)->get('lang_fields', []));
$title = match ($mode ?? 'view') {
    'add' => ($lang_fields['field_management'] ?? 'Field management') . ' - ' . ($lang_fields['text_add'] ?? 'Add'),
    'edit' => ($lang_fields['field_management'] ?? 'Field management') . ' - ' . ($lang_fields['text_edit'] ?? 'Edit'),
    default => $lang_fields['field_management'] ?? 'Field management',
};
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('fields._fields')
@endsection
