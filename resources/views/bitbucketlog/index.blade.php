@php
$title = 'BitBucket Log';
$CURUSER = (array) (\app(\App\Support\CurrentUser::class)->get() ?? []);
$rows = (array) ($rows ?? []);
$count = (int) ($count ?? 0);
$pagertop = (string) ($pagertop ?? '');
$pagerbottom = (string) ($pagerbottom ?? '');
$userDisplayMap = (array) ($userDisplayMap ?? []);
$imageDimensions = (array) ($imageDimensions ?? []);
$isModerator = \App\Support\UserDisplay::currentClass() >= (defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0);
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
<h1>BitBucket Log</h1>
Total Images Stored: {{ $count }}
{{ $pagertop }}

@if (empty($rows))
    <b>BitBucket Log is empty</b>
@else
    <table align='center' border='0' cellspacing='0' cellpadding='5'>
    @foreach ($rows as $row)
        @php
            $id = (int) ($row['id'] ?? 0);
            $name = (string) ($row['name'] ?? '');
            $owner = (int) ($row['owner'] ?? 0);
            $added = (string) ($row['added'] ?? '');
            $date = substr($added, 0, strpos($added, ' '));
            $time = substr($added, strpos($added, ' ') + 1);
            $url = str_replace(' ', '%20', htmlspecialchars("bitbucket/$name"));
            $dim = (array) ($imageDimensions[$id] ?? ['width' => 0, 'height' => 0]);
            $width = (int) ($dim['width'] ?? 0);
            $height = (int) ($dim['height'] ?? 0);
        @endphp
        <tr>
        <td><center><a href={{ $url }}><img src="{{ $url }}" border=0 onLoad='SetSize(this, 400)'></a></center>
        Uploaded by: {!! $userDisplayMap[$owner] ?? \App\Support\UserDisplay::username($owner) !!}<br />
        (#{{ $id }}) Filename: {{ $name }} ({{ $width }}&nbsp;x&nbsp;{{ $height }})
        @if ($isModerator)
            <b><a href="?delete={{ $id }}">[Delete]</a></b><br />
        @endif
        Added: {{ $date }} {{ $time }}
        </tr>
    @endforeach
    </table>
@endif
{{ $pagerbottom }}
@endsection
