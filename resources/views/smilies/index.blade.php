@extends('layouts.legacy')

@section('title', $lang_smilies['text_smilies'] ?? '')

@section('content')
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($smiliesFrame ?? ''))
@endsection
