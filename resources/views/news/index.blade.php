@extends('layouts.legacy')

@section('title', $title ?? ($lang_news['head_site_news'] ?? 'Site news'))

@section('content')
<form id="compose" name="compose" method="post" action="{{ $actionUrl ?? '?action=add' }}">
@csrf
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::composeBegin($composeTitle ?? $title ?? '', ($mode ?? 'add') === 'edit' ? 'edit' : 'new', $body ?? '', true, $subject ?? '', 100)))
<tr><td class="toolbox" align="center" colspan="2"><input type="checkbox" name="notify" value="yes"{{ $checked ?? '' }} />{{ $lang_news['text_notify_users_of_this'] ?? 'Notify users of this' }}</td></tr>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::composeEnd()))
@if (($mode ?? '') === 'edit')
    <input type="hidden" name="returnto" value="{{ $returnto ?? '' }}" />
@endif
</form>
@endsection
