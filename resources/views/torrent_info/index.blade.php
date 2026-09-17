@extends('layouts.legacy')

@section('title', "Torrent Info")

@section('content')
<style type="text/css" nonce="{{ $cspNonce ?? '' }}">

/* list styles */
ul ul { margin-left: 15px; }
ul, li { padding: 0px; margin: 0px; list-style-type: none; color: #000; font-weight: normal;}
ul a, li a { color: #009; text-decoration: none; font-weight: normal; }
li { display: inline; } /* fix for IE blank line bug */
ul > li { display: list-item; }

li div.string  {padding: 3px;}
li div.integer {padding: 3px;}
li div.dictionary {padding: 3px;}
li div.list {padding: 3px;}
li div.string span.icon {color:#090;padding: 2px;}
li div.integer span.icon {color:#990;padding: 2px;}
li div.dictionary span.icon {color:#909;padding: 2px;}
li div.list span.icon {color:#009;padding: 2px;}

li span.title {font-weight: bold;}

</style>
<div align=center><h1>{{ $torrentName ?? '' }}</h1>
<div class="nx-box nx-box--750">
<ul id='torrent-structure'>
{{ $structureHtml ?? '' }}
</ul>
</div>
@endsection
