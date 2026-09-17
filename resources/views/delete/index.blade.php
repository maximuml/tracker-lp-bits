@extends('layouts.legacy')

@section('title', __('legacy/delete.head_torrent_deleted'))

@section('content')
<h1>{{ $message ?? (__('legacy/delete.text_torrent_deleted')) }}</h1>
<p>{{ $ret ?? ('<a href="index.php">'.(__('legacy/delete.text_back_to_index')).'</a>') }}</p>
@endsection
