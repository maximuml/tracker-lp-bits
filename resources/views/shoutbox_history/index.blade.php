@extends('layouts.legacy')

@section('title', $title ?? ($lang_shoutbox['text_history_title'] ?? 'Shoutbox history'))

@section('content')
<script nonce="{{ $cspNonce ?? '' }}">var SHOUT_CSRF = '{{ $csrfToken ?? '' }}';</script>

<h2>{{ $lang_shoutbox['text_history_title'] ?? 'Shoutbox history' }}</h2>
<form action="shoutbox_history.php" method="get">
<table border="0" cellspacing="0" cellpadding="5">
<tr><td>{{ $lang_shoutbox['text_username'] ?? 'Username' }}</td><td><input type="text" name="user" value="{{ $filters['user'] ?? '' }}" /></td>
<td>{{ $lang_shoutbox['text_from'] ?? 'From' }}</td><td><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" /></td>
<td>{{ $lang_shoutbox['text_to'] ?? 'To' }}</td><td><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" /></td></tr>
<tr><td>{{ $lang_shoutbox['text_search'] ?? 'Search' }}</td><td><input type="text" name="search" value="{{ $filters['search'] ?? '' }}" /></td>
<td colspan="4"><input type="submit" class="btn" value="{{ $lang_shoutbox['text_filter'] ?? 'Filter' }}" /></td></tr>
</table></form>

<table border="0" cellspacing="0" cellpadding="2" width="100%">
@foreach ($items ?? [] as $item)
    <tr><td class="shoutrow{{ $item['mentionsMe'] ? ' shoutrow-mentions-me' : '' }}">
    <span class="date">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml('['.$item['time'].']'))</span> @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['actions'])) @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['username'])) @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['reactions']))
    <div>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['messageHtml']))</div>
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
