@php
$lang_shoutbox = (array) (\App\Support\SupportContext::getGlobal('lang_shoutbox') ?? []);
$CURUSER = (array) (\app(\App\Support\CurrentUser::class)->get() ?? []);
$perPage = (int) ($perPage ?? 50);
$page = (int) ($page ?? 1);
$filters = (array) ($filters ?? []);
$rows = (array) ($rows ?? []);
$total = (int) ($total ?? 0);
$currentUserId = (int) ($currentUserId ?? 0);
$isStaff = (bool) ($isStaff ?? false);
$csrfToken = (string) ($csrfToken ?? '');
$reactionData = (array) ($reactionData ?? ['counts' => [], 'mine' => [], 'users' => []]);
$userDisplayMap = (array) ($userDisplayMap ?? []);
$reactionCounts = $reactionData['counts'] ?? [];
$reactionMine = $reactionData['mine'] ?? [];
$reactionUsers = $reactionData['users'] ?? [];
$formAction = 'shoutbox_history.php';
$title = $title ?? ($lang_shoutbox['text_history_title'] ?? 'Shoutbox history');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
<script>var SHOUT_CSRF = '{{ htmlspecialchars($csrfToken) }}';</script>

<h2>{{ $lang_shoutbox['text_history_title'] ?? 'Shoutbox history' }}</h2>
<form action="{{ htmlspecialchars($formAction) }}" method="get">
<table border="0" cellspacing="0" cellpadding="5">
<tr><td>{{ $lang_shoutbox['text_username'] ?? 'Username' }}</td><td><input type="text" name="user" value="{{ htmlspecialchars($filters['user'] ?? '') }}" /></td>
<td>{{ $lang_shoutbox['text_from'] ?? 'From' }}</td><td><input type="date" name="from" value="{{ htmlspecialchars($filters['from'] ?? '') }}" /></td>
<td>{{ $lang_shoutbox['text_to'] ?? 'To' }}</td><td><input type="date" name="to" value="{{ htmlspecialchars($filters['to'] ?? '') }}" /></td></tr>
<tr><td>{{ $lang_shoutbox['text_search'] ?? 'Search' }}</td><td><input type="text" name="search" value="{{ htmlspecialchars($filters['search'] ?? '') }}" /></td>
<td colspan="4"><input type="submit" class="btn" value="{{ htmlspecialchars($lang_shoutbox['text_filter'] ?? 'Filter') }}" /></td></tr>
</table></form>

<table border="0" cellspacing="0" cellpadding="2" width="100%">
@foreach ($rows as $arr)
    @php
        $time = \App\Support\Shoutbox::formatTime((int) $arr['date'], true);
        $uid = (int) $arr['userid'];
        $username = $uid > 0 ? ($userDisplayMap[$uid] ?? '') : ($lang_shoutbox['text_guest'] ?? '<b>Guest</b>');
        $shoutId = (int) $arr['id'];
        $actions = \App\Support\Shoutbox::renderActions($arr, $currentUserId, $isStaff);
        $reactions = \App\Support\Shoutbox::renderReactions(
            $shoutId,
            $currentUserId,
            $reactionCounts[$shoutId] ?? [],
            $reactionMine[$shoutId] ?? [],
            $reactionUsers[$shoutId] ?? []
        );
        $mentionsMe = false;
        $message = \App\Support\Shoutbox::formatMessage($arr['text'], $currentUserId, $mentionsMe);
        $editedNote = '';
        if (! empty($arr['edited_at']) && (int) $arr['edited_at'] > 0) {
            $editedNote = ' <span class="shout-edited-note">('.htmlspecialchars((string) ($lang_shoutbox['text_edited'] ?? 'edited')).' '.\App\Support\Shoutbox::formatTime((int) $arr['edited_at'], true).')</span>';
        }
        $messageHtml = '<span id="shout-msg-'.$shoutId.'" class="shout-msg" data-raw="'.htmlspecialchars((string) $arr['text'], ENT_QUOTES).'">'.$message.'</span>'.$editedNote;
    @endphp
    <tr><td class="shoutrow{{ $mentionsMe ? ' shoutrow-mentions-me' : '' }}">
    <span class="date">[{{ $time }}]</span> {!! $actions !!} {!! $username !!} {!! $reactions !!}
    <div>{!! $messageHtml !!}</div>
    </td></tr>
@endforeach
</table>

@php
    $totalPages = (int) ceil($total / $perPage);
    if ($totalPages > 1) {
        $base = $formAction.'?'.http_build_query(array_filter($filters, fn ($v) => $v !== '')).'&page=';
    }
@endphp
@if ($totalPages > 1 ?? false)
    <div class="pagination">
    @for ($i = 1; $i <= $totalPages; $i++)
        @if ($i == $page)
            <b>{{ $i }}</b>
        @else
            <a href="{{ htmlspecialchars($base.$i) }}">{{ $i }}</a>
        @endif
    @endfor
    </div>
@endif
@endsection
