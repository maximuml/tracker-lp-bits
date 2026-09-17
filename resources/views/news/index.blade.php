@extends('layouts.legacy')

@section('title', $title ?? (__('legacy/news.head_site_news')))

@section('content')
<form id="compose" name="compose" method="post" action="{{ $actionUrl ?? '?action=add' }}">
@csrf
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::composeBegin($composeTitle ?? $title ?? '', ($mode ?? 'add') === 'edit' ? 'edit' : 'new', $body ?? '', true, $subject ?? '', 100)))
<tr><td class="toolbox" align="center" colspan="2"><input type="checkbox" name="notify" value="yes"{{ $checked ?? '' }} />{{ __('legacy/news.text_notify_users_of_this')}}</td></tr>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::composeEnd()))
@if (($mode ?? '') === 'edit')
    <input type="hidden" name="returnto" value="{{ $returnto ?? '' }}" />
@endif
</form>
@endsection
