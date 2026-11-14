@extends('layouts.app')

@section('title', "Torrent Info")

@section('content')
<x-page :title="($torrentName ?? '')" width="list">
<div class="nx-box nx-box--750">
<ul id="torrent-structure">
{{ $structureHtml ?? '' }}
</ul>
</div>
</x-page>
@endsection
