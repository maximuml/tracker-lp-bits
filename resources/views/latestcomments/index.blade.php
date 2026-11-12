@extends('layouts.app')

@section('title', $title ?? (__('functions.text_latest_comments')))

@section('content')
<x-page :title="__('functions.text_latest_comments')" width="list">
@if ($count == 0)
    <x-empty-state :title="__('functions.text_no_comments')" />
@else
    <x-pagination :page="$pager['page']" :pages="$pager['pages']" :href="$pager['href']" :prev-label="$pager['prevLabel']" :next-label="$pager['nextLabel']" :zero-based="$pager['zeroBased']" label="Pagination top" />
    @foreach ($rows as $row)
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
    <x-pagination :page="$pager['page']" :pages="$pager['pages']" :href="$pager['href']" :prev-label="$pager['prevLabel']" :next-label="$pager['nextLabel']" :zero-based="$pager['zeroBased']" label="Pagination bottom" />
@endif
</x-page>
@endsection
