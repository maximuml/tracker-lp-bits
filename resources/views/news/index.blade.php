@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title ?? (__('legacy/news.head_site_news')))

@section('content')
<form id="compose" name="compose" method="post" action="{{ $actionUrl ?? '/web/news/add' }}">
@csrf
<x-compose :title="$composeTitle ?? $title ?? ''" :type="($mode ?? 'add') === 'edit' ? 'edit' : 'new'" :body="$body ?? ''" :has-subject="true" :subject="$subject ?? ''">
<tr><td class="toolbox text-center" colspan="2"><input type="checkbox" name="notify" value="yes"{{ $checked ?? '' }} />{{ __('legacy/news.text_notify_users_of_this')}}</td></tr>
</x-compose>
@if (($mode ?? '') === 'edit')
    <input type="hidden" name="returnto" value="{{ $returnto ?? '' }}" />
@endif
</form>
@endsection
