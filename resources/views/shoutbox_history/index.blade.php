@extends('layouts.app')

@section('title', $title ?? (__('shoutbox.text_history_title')))

@section('content')
<script nonce="{{ $cspNonce ?? '' }}">var SHOUT_CSRF = '{{ $csrfToken ?? '' }}';@include('shoutbox._js_lang')</script>
<script src="{{ \App\Support\AssetAppender::versionedSrc('js/shoutbox.js') }}" type="text/javascript"></script>

<x-page :title="__('shoutbox.text_history_title')" width="list">
<section class="nx-idx-card">
<form action="/web/shoutbox_history" method="get">
<div class="flex items-start flex-wrap">
<div class="p-[5px]">{{ __('shoutbox.text_username')}}</div><div class="p-[5px]"><input type="text" name="user" value="{{ $filters['user'] ?? '' }}" aria-label="{{ __('shoutbox.text_username') }}" /></div>
<div class="p-[5px]">{{ __('shoutbox.text_from')}}</div><div class="p-[5px]"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="{{ __('shoutbox.text_from') }}" /></div>
<div class="p-[5px]">{{ __('shoutbox.text_to')}}</div><div class="p-[5px]"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="{{ __('shoutbox.text_to') }}" /></div></div>
<div class="flex items-start flex-wrap">
<div class="p-[5px]">{{ __('shoutbox.text_search')}}</div><div class="p-[5px]"><input type="text" name="search" value="{{ $filters['search'] ?? '' }}" aria-label="{{ __('shoutbox.text_search') }}" /></div>
<div class="p-[5px]"><input type="submit" class="nx-btn nx-btn--primary" value="{{ __('shoutbox.text_filter')}}" /></div></div>
</form>
</section>

<section class="nx-idx-card">
@if (empty($items))
<x-empty-state :title="__('shoutbox.text_history_empty')" />
@else
<x-data-table :caption="__('shoutbox.text_history_title')" captionHidden>
@foreach ($items ?? [] as $item)
    <tr><td class="shoutrow{{ $item['mentionsMe'] ? ' shoutrow-mentions-me' : '' }}">
    <span class="date">[{{ $item['time'] }}]</span> @if ($item['actions'] !== null)<x-shoutbox.actions :msg-id="$item['actions']['msgId']" :can-edit="$item['actions']['canEdit']" :can-delete="$item['actions']['canDelete']" />@endif {{ $item['username'] }} @if ($item['reactions'] !== null)<x-shoutbox.reactions :shout-id="$item['reactions']['shoutId']" :reactions="$item['reactions']['reactions']" :show-picker="$item['reactions']['showPicker']" :picker-emojis="$item['reactions']['pickerEmojis']" />@endif
    <div><x-shoutbox.message :id="$item['msgId']" :is-long="$item['msgLong']" :raw="$item['msgRaw']" :formatted="$item['msgFormatted']" :edited-time="$item['editedTime']" /></div>
    </td></tr>
@endforeach
</x-data-table>
@endif
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
</x-page>
@endsection
