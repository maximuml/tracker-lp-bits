@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title ?? (__('functions.text_latest_comments')))

@section('content')
@if ($count == 0)
    <x-std-message heading="Sorry" :text="__('functions.text_no_comments')" :htmlstrip="false" />
@else
    {{ $pagertop }}
    <h1 class="text-center">{{ __('functions.text_latest_comments')}}</h1>
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
    {{ $pagerbottom }}
@endif
@endsection
