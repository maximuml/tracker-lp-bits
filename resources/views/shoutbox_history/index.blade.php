@extends('layouts.legacy')

@section('title', $title ?? (__('legacy/shoutbox.text_history_title')))

@section('content')
<script nonce="{{ $cspNonce ?? '' }}">var SHOUT_CSRF = '{{ $csrfToken ?? '' }}';</script>

<h2>{{ __('legacy/shoutbox.text_history_title')}}</h2>
<form action="shoutbox_history.php" method="get">
<div class="nx-row">
<div class="nx-cell-5">{{ __('legacy/shoutbox.text_username')}}</div><div class="nx-cell-5"><input type="text" name="user" value="{{ $filters['user'] ?? '' }}" /></div>
<div class="nx-cell-5">{{ __('legacy/shoutbox.text_from')}}</div><div class="nx-cell-5"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" /></div>
<div class="nx-cell-5">{{ __('legacy/shoutbox.text_to')}}</div><div class="nx-cell-5"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" /></div></div>
<div class="nx-row">
<div class="nx-cell-5">{{ __('legacy/shoutbox.text_search')}}</div><div class="nx-cell-5"><input type="text" name="search" value="{{ $filters['search'] ?? '' }}" /></div>
<div class="nx-cell-5"><input type="submit" class="btn" value="{{ __('legacy/shoutbox.text_filter')}}" /></div></div>
</form>

<table data-nx="data" border="0" cellspacing="0" cellpadding="2" width="100%">
@foreach ($items ?? [] as $item)
    <tr><td class="shoutrow{{ $item['mentionsMe'] ? ' shoutrow-mentions-me' : '' }}">
    <span class="date">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml('['.$item['time'].']'))</span> {{ $item['actions'] }} {{ $item['username'] }} {{ $item['reactions'] }}
    <div>{{ $item['messageHtml'] }}</div>
    </td></tr>
@endforeach
</table>

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
