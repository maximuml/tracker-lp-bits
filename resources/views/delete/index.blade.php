@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('legacy/delete.head_torrent_deleted'))

@section('content')
<h1>{{ $message ?? (__('legacy/delete.text_torrent_deleted')) }}</h1>
<p>@if(($returnto ?? '') !== '')<a href="{{ $returnto }}">{{ __('legacy/delete.text_go_back') }}</a>@else<a href="/web/index">{{ __('legacy/delete.text_back_to_index') }}</a>@endif</p>
@endsection
