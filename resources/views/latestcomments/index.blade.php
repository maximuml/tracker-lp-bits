@extends('layouts.app')

@section('title', $title ?? (__('functions.text_latest_comments')))

@section('content')
<x-page :title="__('functions.text_latest_comments')" width="list">
@if ($vm->count == 0)
    <x-empty-state :title="__('functions.text_no_comments')" />
@else
    <x-pagination :page="$vm->pager['page']" :pages="$vm->pager['pages']" :href="$vm->pager['href']" :prev-label="$vm->pager['prevLabel']" :next-label="$vm->pager['nextLabel']" :zero-based="$vm->pager['zeroBased']" label="Pagination top" />
    @foreach ($vm->rows as $row)
        <div>
            <div id="cid{{ $row['id'] }}" class="nx-embedded">
                        #{{ $row['id'] }}&nbsp;&nbsp;
                        <span class="text-nxm-text-dim">{{ __('functions.text_by')}}</span>
                        {{ $row['usernameHtml'] ?? '' }}
                        &nbsp;&nbsp;<span class="text-nxm-text-dim">{{ __('functions.text_at')}}</span>
                        {{ $row['timeHtml'] ?? '' }}
                        @if(($row['parentUrl'] ?? '') !== '') <span class="text-nxm-text-dim">on</span> <a href="{{ $row['parentUrl'] }}">{{ $row['parent_name'] ?? '' }}</a>@endif
            </div>
            <div class="nx-main flex items-start">
                <div class="flex-[0_0_150px]">
                        {{ $row['avatarHtml'] ?? '' }}
                </div>
                <div class="grow p-[5px] break-all">
                        <br />
                        {{ $row['commentHtml'] ?? '' }}
                </div>
            </div>
        </div>
    @endforeach
    <x-pagination :page="$vm->pager['page']" :pages="$vm->pager['pages']" :href="$vm->pager['href']" :prev-label="$vm->pager['prevLabel']" :next-label="$vm->pager['nextLabel']" :zero-based="$vm->pager['zeroBased']" label="Pagination bottom" />
@endif
</x-page>
@endsection
