@extends('layouts.legacy')

@section('title', __('legacy/delete.head_torrent_deleted'))

@section('content')
<h1>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($message ?? (__('legacy/delete.text_torrent_deleted'))))</h1>
<p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($ret ?? ('<a href="index.php">'.(__('legacy/delete.text_back_to_index')).'</a>')))</p>
@endsection
