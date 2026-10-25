@extends('layouts.app')

@section('title', $title ?? (__('shoutbox.text_history_title')))

@section('content')
<script nonce="{{ $cspNonce ?? '' }}">var SHOUT_CSRF = '{{ $csrfToken ?? '' }}';window.SHOUT_LANG = {{ Illuminate\Support\Js::from([
    'requestFailed' => __('shoutbox.js_request_failed'),
    'invalidResponse' => __('shoutbox.js_invalid_response'),
    'spoilerTitle' => __('shoutbox.js_spoiler_title'),
    'quoteAuthor' => __('shoutbox.js_quote_author'),
    'url' => __('shoutbox.js_url'),
    'linkText' => __('shoutbox.js_link_text'),
    'confirmDelete' => __('shoutbox.js_confirm_delete'),
    'collapse' => __('shoutbox.js_collapse'),
    'expand' => __('shoutbox.js_expand'),
    'newMentions' => __('shoutbox.js_new_mentions'),
]) }};</script>
<script src="{{ \App\Support\AssetAppender::versionedSrc('js/shoutbox.js') }}" type="text/javascript"></script>

<section class="nx-idx-card">
<h2>{{ __('shoutbox.text_history_title')}}</h2>
<form action="/web/shoutbox_history" method="get">
<div class="flex items-start">
<div class="p-[5px]">{{ __('shoutbox.text_username')}}</div><div class="p-[5px]"><input type="text" name="user" value="{{ $filters['user'] ?? '' }}" /></div>
<div class="p-[5px]">{{ __('shoutbox.text_from')}}</div><div class="p-[5px]"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" /></div>
<div class="p-[5px]">{{ __('shoutbox.text_to')}}</div><div class="p-[5px]"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" /></div></div>
<div class="flex items-start">
<div class="p-[5px]">{{ __('shoutbox.text_search')}}</div><div class="p-[5px]"><input type="text" name="search" value="{{ $filters['search'] ?? '' }}" /></div>
<div class="p-[5px]"><input type="submit" class="nx-btn nx-btn--primary" value="{{ __('shoutbox.text_filter')}}" /></div></div>
</form>
</section>

<section class="nx-idx-card">
<x-data-table :caption="__('shoutbox.text_history_title')" captionHidden>
@foreach ($items ?? [] as $item)
    <tr><td class="shoutrow{{ $item['mentionsMe'] ? ' shoutrow-mentions-me' : '' }}">
    <span class="date">[{{ $item['time'] }}]</span> @if ($item['actions'] !== null)<x-shoutbox.actions :msg-id="$item['actions']['msgId']" :can-edit="$item['actions']['canEdit']" :can-delete="$item['actions']['canDelete']" />@endif {{ $item['username'] }} @if ($item['reactions'] !== null)<x-shoutbox.reactions :shout-id="$item['reactions']['shoutId']" :reactions="$item['reactions']['reactions']" :show-picker="$item['reactions']['showPicker']" :picker-emojis="$item['reactions']['pickerEmojis']" />@endif
    <div><x-shoutbox.message :id="$item['msgId']" :is-long="$item['msgLong']" :raw="$item['msgRaw']" :formatted="$item['msgFormatted']" :edited-time="$item['editedTime']" /></div>
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
