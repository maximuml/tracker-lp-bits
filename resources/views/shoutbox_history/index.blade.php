@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title ?? (__('legacy/shoutbox.text_history_title')))

@section('content')
<script nonce="{{ $cspNonce ?? '' }}">var SHOUT_CSRF = '{{ $csrfToken ?? '' }}';</script>

<section class="nx-idx-card">
<h2>{{ __('legacy/shoutbox.text_history_title')}}</h2>
<form action="shoutbox_history.php" method="get">
<div class="flex items-start">
<div class="p-[5px]">{{ __('legacy/shoutbox.text_username')}}</div><div class="p-[5px]"><input type="text" name="user" value="{{ $filters['user'] ?? '' }}" /></div>
<div class="p-[5px]">{{ __('legacy/shoutbox.text_from')}}</div><div class="p-[5px]"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" /></div>
<div class="p-[5px]">{{ __('legacy/shoutbox.text_to')}}</div><div class="p-[5px]"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" /></div></div>
<div class="flex items-start">
<div class="p-[5px]">{{ __('legacy/shoutbox.text_search')}}</div><div class="p-[5px]"><input type="text" name="search" value="{{ $filters['search'] ?? '' }}" /></div>
<div class="p-[5px]"><input type="submit" class="btn" value="{{ __('legacy/shoutbox.text_filter')}}" /></div></div>
</form>
</section>

<section class="nx-idx-card">
<x-data-table :caption="__('legacy/shoutbox.text_history_title')" captionHidden>
@foreach ($items ?? [] as $item)
    <tr><td class="shoutrow{{ $item['mentionsMe'] ? ' shoutrow-mentions-me' : '' }}">
    <span class="date">[{{ $item['time'] }}]</span> {{ $item['actions'] }} {{ $item['username'] }} {{ $item['reactions'] }}
    <div>@include('shoutbox._message', ['id' => $item['msgId'], 'isLong' => $item['msgLong'], 'raw' => $item['msgRaw'], 'formatted' => $item['msgFormatted'], 'editedTime' => $item['editedTime'], 'labelMore' => '', 'labelLess' => ''])</div>
    </td></tr>
@endforeach
</x-data-table>
</section>

@if (($totalPages ?? 0) > 1)
    <div class="pagination">
    @for ($i = 1; $i <= $totalPages; $i++)
        @if ($i == ($page ?? 1))
            <b>{{ $i }}</b>
        @else
            <a href="{{ ($paginationBase ?? '').$i }}">{{ $i }}</a>
        @endif
    @endfor
    </div>
@endif
@endsection
