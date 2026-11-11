@extends('layouts.app')

@section('title', {{ __('thank.page_title') }})

@section('content')
<x-std-message heading="Thanks" :text="\App\Support\Html\SafeHtml::fromUntrustedHtml($message ?? '')" :htmlstrip="false" />
<p class="text-center"><a href='/web/details/{{ $torrentid ?? 0 }}'>{{ __('thanks.back_to_torrent') }}</a></p>
@endsection
