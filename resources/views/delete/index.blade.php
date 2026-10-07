@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('delete.head_torrent_deleted'))

@section('content')
<h1>{{ $message ?? (__('delete.text_torrent_deleted')) }}</h1>
<p>@if(($returnto ?? '') !== '')<a href="{{ $returnto }}">{{ __('delete.text_go_back') }}</a>@else<a href="/web/index">{{ __('delete.text_back_to_index') }}</a>@endif</p>
@endsection
