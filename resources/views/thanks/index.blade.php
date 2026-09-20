@extends('layouts.legacy')

@section('title', 'Thanks')

@section('content')
<x-std-message heading="Thanks" :text="\App\Support\Html\SafeHtml::fromUntrustedHtml($message ?? '')" :htmlstrip="false" />
<p align='center'><a href='details.php?id={{ $torrentid ?? 0 }}'>Back to torrent</a></p>
@endsection
