@extends('layouts.legacy')

@section('title', 'BitBucket Log')

@section('content')
<h1>BitBucket Log</h1>
Total Images Stored: {{ $count ?? 0 }}
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagertop ?? ''))

@if (empty($items ?? []))
    <b>BitBucket Log is empty</b>
@else
    <table align='center' border='0' cellspacing='0' cellpadding='5'>
    @foreach ($items as $item)
        <tr>
        <td><center><a href="{{ $item['url'] }}"><img src="{{ $item['url'] }}" border=0 class="bitbucket-shot"></a></center>
        Uploaded by: @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['usernameHtml']))<br />
        (#{{ $item['id'] }}) Filename: {{ $item['name'] }} ({{ $item['width'] }}&nbsp;x&nbsp;{{ $item['height'] }})
        @if ($isModerator ?? false)
            <b><a href="?delete={{ $item['id'] }}">[Delete]</a></b><br />
        @endif
        Added: {{ $item['date'] }} {{ $item['time'] }}
        </tr>
    @endforeach
    </table>
@endif
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
@endsection
