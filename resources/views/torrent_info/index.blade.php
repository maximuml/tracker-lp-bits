@extends('layouts.app')

@section('title', "Torrent Info")

@section('content')
<style type="text/css" nonce="{{ $cspNonce ?? '' }}">

/* list styles — scoped to the structure tree so the chrome lists are untouched */
#torrent-structure, #torrent-structure ul, #torrent-structure li { padding: 0px; margin: 0px; list-style-type: none; color: var(--nxm-text); font-weight: normal;}
#torrent-structure ul { margin-left: 15px; }
#torrent-structure a { color: var(--nxm-accent-text); text-decoration: none; font-weight: normal; }
#torrent-structure li { display: list-item; }

#torrent-structure li div.string  {padding: 3px;}
#torrent-structure li div.integer {padding: 3px;}
#torrent-structure li div.dictionary {padding: 3px;}
#torrent-structure li div.list {padding: 3px;}
#torrent-structure li div.string span.icon {color:#090;padding: 2px;}
#torrent-structure li div.integer span.icon {color:#990;padding: 2px;}
#torrent-structure li div.dictionary span.icon {color:#909;padding: 2px;}
#torrent-structure li div.list span.icon {color:#009;padding: 2px;}

#torrent-structure li span.title {font-weight: bold;}

</style>
<x-page :title="($torrentName ?? '')" width="list">
<div class="nx-box nx-box--750">
<ul id="torrent-structure">
{{ $structureHtml ?? '' }}
</ul>
</div>
</x-page>
@endsection
