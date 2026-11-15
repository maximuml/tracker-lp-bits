@extends('layouts.app')

@section('title', $vm->title ?? (__('news.head_site_news')))

@section('content')
<form id="compose" name="compose" class="nx-compose" method="post" action="{{ $vm->actionUrl ?? '/web/news/add' }}">
@csrf
<x-compose :title="$vm->composeTitle ?? $vm->title ?? ''" :type="($vm->mode ?? 'add') === 'edit' ? 'edit' : 'new'" :body="$vm->body ?? ''" :has-subject="true" :subject="$vm->subject ?? ''">
<label><input type="checkbox" name="notify" value="yes"{{ $vm->checked ?? '' }} /> {{ __('news.text_notify_users_of_this')}}</label>
</x-compose>
@if (($vm->mode ?? '') === 'edit')
    <input type="hidden" name="returnto" value="{{ $vm->returnto ?? '' }}" />
@endif
</form>
@endsection
