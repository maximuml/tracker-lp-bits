@extends('layouts.app')

@section('title', 'BitBucket Log')

@section('content')
<h1>BitBucket Log</h1>
Total Images Stored: {{ $count ?? 0 }}
{{ $pagertop ?? '' }}

@if (empty($items ?? []))
    <b>BitBucket Log is empty</b>
@else
    <x-data-table caption="BitBucket Log" captionHidden>
    @foreach ($items as $item)
        <tr>
        <td><div class="text-center"><a href="{{ $item['url'] }}"><img src="{{ $item['url'] }}" class="bitbucket-shot" alt="{{ $item['name'] }}" loading="lazy" decoding="async"></a></div>
        Uploaded by: {{ $item['usernameHtml'] }}<br />
        (#{{ $item['id'] }}) Filename: {{ $item['name'] }} ({{ $item['width'] }}&nbsp;x&nbsp;{{ $item['height'] }})
        @if ($isModerator ?? false)
            <b><form method="post" action="/web/admin/bitbucket-log" class="nx-inline">@csrf<input type="hidden" name="delete" value="{{ $item['id'] }}" /><button type="submit" class="nxm-linkbtn font-bold">[Delete]</button></form></b><br />
        @endif
        Added: {{ $item['date'] }} {{ $item['time'] }}</td>
        </tr>
    @endforeach
    </x-data-table>
@endif
{{ $pagerbottom ?? '' }}
@endsection
