@extends('layouts.legacy')

@section('title', $title ?? (__('legacy/functions.text_latest_comments')))

@section('content')
@if ($count == 0)
    {{ \App\Support\Frame::stdMessage(('Sorry'), __('legacy/functions.text_no_comments'), false) }}
@else
    {{ $pagertop }}
    <h1 align="center">{{ __('legacy/functions.text_latest_comments')}}</h1>
    @foreach ($rows as $row)
        <div>
            <div id="cid{{ $row['id'] }}" class="nx-embedded">
                        #{{ $row['id'] }}&nbsp;&nbsp;
                        <font color="gray">{{ __('legacy/functions.text_by')}}</font>
                        {{ $row['usernameHtml'] ?? '' }}
                        &nbsp;&nbsp;<font color="gray">{{ __('legacy/functions.text_at')}}</font>
                        {{ $row['timeHtml'] ?? '' }}
                        {{ $row['parentLinkHtml'] ?? '' }}
            </div>
            <div class="nx-main nx-row">
                <div class="nx-w-150">
                        {{ $row['avatarHtml'] ?? '' }}
                </div>
                <div class="nx-grow nx-cell-5 word-break-all">
                        <br />
                        {{ $row['commentHtml'] ?? '' }}
                </div>
            </div>
        </div>
    @endforeach
    {{ $pagerbottom }}
@endif
@endsection
