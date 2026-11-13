@extends('layouts.app')

@section('title', __('log.bitbucket_log'))

@section('content')
<x-page :title="'BitBucket Log'" width="list">
Total Images Stored: {{ $count ?? 0 }}
<x-pagination :page="$pager['page']" :pages="$pager['pages']" :href="$pager['href']" :prev-label="$pager['prevLabel']" :next-label="$pager['nextLabel']" :zero-based="$pager['zeroBased']" label="Pagination top" />

@if (empty($items ?? []))
    <x-empty-state :title="__('log.bitbucket_log_empty')" />
@else
    <x-data-table caption="BitBucket Log" captionHidden>
    @foreach ($items as $item)
        <tr>
        <td><div class="text-center"><a href="{{ $item['url'] }}"><img src="{{ $item['url'] }}" class="bitbucket-shot" alt="{{ $item['name'] }}" loading="lazy" decoding="async"></a></div>
        Uploaded by: {{ $item['usernameHtml'] }}<br />
        (#{{ $item['id'] }}) Filename: {{ $item['name'] }} ({{ $item['width'] }}&nbsp;x&nbsp;{{ $item['height'] }})
        @if ($isModerator ?? false)
            <b><form method="post" action="/web/admin/bitbucket-log" class="nx-inline">@csrf<input type="hidden" name="delete" value="{{ $item['id'] }}" /><button type="submit" class="nx-postbtn nx-postbtn--danger">[Delete]</button></form></b><br />
        @endif
        Added: {{ $item['date'] }} {{ $item['time'] }}</td>
        </tr>
    @endforeach
    </x-data-table>
@endif
<x-pagination :page="$pager['page']" :pages="$pager['pages']" :href="$pager['href']" :prev-label="$pager['prevLabel']" :next-label="$pager['nextLabel']" :zero-based="$pager['zeroBased']" label="Pagination bottom" />
</x-page>
@endsection
