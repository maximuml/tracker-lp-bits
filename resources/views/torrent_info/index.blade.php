@extends('layouts.app')

@section('title', "Torrent Info")

@section('content')
<x-page :title="($vm->torrentName ?? '')" width="list">
<div class="nx-box nx-box--750">
<ul id="torrent-structure">
{{ $vm->structureHtml ?? '' }}
</ul>
</div>
</x-page>
@endsection
