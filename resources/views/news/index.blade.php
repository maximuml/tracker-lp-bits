@extends('layouts.app')

@section('title', $title ?? (__('news.head_site_news')))

@section('content')
<form id="compose" name="compose" method="post" action="{{ $actionUrl ?? '/web/news/add' }}">
@csrf
<x-compose :title="$composeTitle ?? $title ?? ''" :type="($mode ?? 'add') === 'edit' ? 'edit' : 'new'" :body="$body ?? ''" :has-subject="true" :subject="$subject ?? ''">
<div class="text-center"><label><input type="checkbox" name="notify" value="yes"{{ $checked ?? '' }} /> {{ __('news.text_notify_users_of_this')}}</label></div>
</x-compose>
@if (($mode ?? '') === 'edit')
    <input type="hidden" name="returnto" value="{{ $returnto ?? '' }}" />
@endif
</form>
@endsection
