
@extends('layouts.legacy')

@section('title', match ($mode ?? 'view') {
    'add' => ($lang_fields['field_management'] ?? 'Field management') . ' - ' . ($lang_fields['text_add'] ?? 'Add'),
    'edit' => ($lang_fields['field_management'] ?? 'Field management') . ' - ' . ($lang_fields['text_edit'] ?? 'Edit'),
    default => $lang_fields['field_management'] ?? 'Field management',
})

@section('content')
@include('fields._fields')
@endsection
