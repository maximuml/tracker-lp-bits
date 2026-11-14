@extends('layouts.app')

@section('title', __('log.bitbucket_log'))

@section('content')
<x-page :title="'BitBucket Log'" width="list">
Total Images Stored: {{ $vm->count ?? 0 }}
<x-pagination :page="$vm->pager['page']" :pages="$vm->pager['pages']" :href="$vm->pager['href']" :prev-label="$vm->pager['prevLabel']" :next-label="$vm->pager['nextLabel']" :zero-based="$vm->pager['zeroBased']" label="Pagination top" />

@if (empty($vm->items ?? []))
    <x-empty-state :title="__('log.bitbucket_log_empty')" />
@else
    <x-data-table caption="BitBucket Log" captionHidden>
    @foreach ($vm->items as $item)
        <tr>
        <td><div class="text-center"><a href="{{ $item['url'] }}"><img src="{{ $item['url'] }}" class="bitbucket-shot" alt="{{ $item['name'] }}" loading="lazy" decoding="async"></a></div>
        Uploaded by: {{ $item['usernameHtml'] }}<br />
        (#{{ $item['id'] }}) Filename: {{ $item['name'] }} ({{ $item['width'] }}&nbsp;x&nbsp;{{ $item['height'] }})
        @if ($vm->isModerator ?? false)
            <b><form method="post" action="/web/admin/bitbucket-log" class="nx-inline">@csrf<input type="hidden" name="delete" value="{{ $item['id'] }}" /><button type="submit" class="nx-postbtn nx-postbtn--danger">[Delete]</button></form></b><br />
        @endif
        Added: {{ $item['date'] }} {{ $item['time'] }}</td>
        </tr>
    @endforeach
    </x-data-table>
@endif
<x-pagination :page="$vm->pager['page']" :pages="$vm->pager['pages']" :href="$vm->pager['href']" :prev-label="$vm->pager['prevLabel']" :next-label="$vm->pager['nextLabel']" :zero-based="$vm->pager['zeroBased']" label="Pagination bottom" />
</x-page>
@endsection
