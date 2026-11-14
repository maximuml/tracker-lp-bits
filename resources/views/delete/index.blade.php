@extends('layouts.app')

@section('title', __('delete.head_torrent_deleted'))

@section('content')
<x-page :title="($vm->message ?? __('delete.text_torrent_deleted'))" width="form">
<p>@if(($vm->returnto ?? '') !== '')<a href="{{ $vm->returnto }}">{{ __('delete.text_go_back') }}</a>@else<a href="/web/index">{{ __('delete.text_back_to_index') }}</a>@endif</p>
</x-page>
@endsection
