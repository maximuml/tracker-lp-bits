@extends('layouts.legacy')

@section('title', 'Thanks')

@section('content')
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::stdMessage('Thanks', $message ?? '', false)))
<p align='center'><a href='details.php?id={{ $torrentid ?? 0 }}'>Back to torrent</a></p>
@endsection
