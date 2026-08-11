@extends('layouts.legacy')

@section('title', $lang_smilies['text_smilies'] ?? '')

@section('content')
    @php \App\Support\Html::smiliesFrame(); @endphp
@endsection
