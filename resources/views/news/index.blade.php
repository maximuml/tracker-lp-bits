@php
$lang_news = (array) (\app(\App\Support\Globals::class)->get('lang_news') ?? []);
$mode = (string) ($mode ?? 'add');
$newsid = (int) ($newsid ?? 0);
$body = (string) ($body ?? '');
$subject = (string) ($subject ?? '');
$notify = (string) ($notify ?? 'no');
$returnto = (string) ($returnto ?? '');
$checked = $notify === 'yes' ? ' checked="checked"' : '';
$actionUrl = $mode === 'edit' ? htmlspecialchars('?action=edit&newsid='.$newsid) : '?action=add';
$composeTitle = $title ?? ($lang_news['text_submit_news_item'] ?? 'Submit news item');
$title = $title ?? ($mode === 'edit' && $newsid > 0
    ? ($lang_news['head_edit_site_news'] ?? 'Edit site news')
    : ($lang_news['head_site_news'] ?? 'Site news'));
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
<form id="compose" name="compose" method="post" action="{{ $actionUrl }}">
@csrf
{!! \App\Support\Frame::composeBegin($composeTitle, $mode === 'edit' ? 'edit' : 'new', $body, true, $subject, 100) !!}
<tr><td class="toolbox" align="center" colspan="2"><input type="checkbox" name="notify" value="yes"{{ $checked }} />{{ $lang_news['text_notify_users_of_this'] ?? 'Notify users of this' }}</td></tr>
{!! \App\Support\Frame::composeEnd() !!}
@if ($mode === 'edit')
    <input type="hidden" name="returnto" value="{{ $returnto }}" />
@endif
</form>
@endsection
