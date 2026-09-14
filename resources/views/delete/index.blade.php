@extends('layouts.legacy')

@section('title', $lang_delete['head_torrent_deleted'] ?? 'Torrent deleted')

@section('content')
<h1>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($message ?? ($lang_delete['text_torrent_deleted'] ?? 'Torrent deleted.')))</h1>
<p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($ret ?? ('<a href="index.php">'.($lang_delete['text_back_to_index'] ?? 'Back to index').'</a>')))</p>
@endsection
