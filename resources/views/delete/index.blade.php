@extends('layouts.app')

@section('title', __('delete.head_torrent_deleted'))

@section('content')
<x-page :title="($message ?? __('delete.text_torrent_deleted'))" width="form">
<p>@if(($returnto ?? '') !== '')<a href="{{ $returnto }}">{{ __('delete.text_go_back') }}</a>@else<a href="/web/index">{{ __('delete.text_back_to_index') }}</a>@endif</p>
</x-page>
@endsection
