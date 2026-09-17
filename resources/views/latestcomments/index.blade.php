@extends('layouts.legacy')

@section('title', $title ?? (__('legacy/functions.text_latest_comments')))

@section('content')
@if ($count == 0)
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::stdMessage(('Sorry'), __('legacy/functions.text_no_comments'), false)))
@else
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagertop))
    <h1 align="center">{{ __('legacy/functions.text_latest_comments')}}</h1>
    @foreach ($rows as $row)
        <div style="margin-top: 8pt; margin-bottom: 8pt;">
            <div id="cid{{ $row['id'] }}" class="nx-embedded">
                        #{{ $row['id'] }}&nbsp;&nbsp;
                        <font color="gray">{{ __('legacy/functions.text_by')}}</font>
                        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['usernameHtml'] ?? ''))
                        &nbsp;&nbsp;<font color="gray">{{ __('legacy/functions.text_at')}}</font>
                        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['timeHtml'] ?? ''))
                        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['parentLinkHtml'] ?? ''))
            </div>
            <div class="nx-main nx-row">
                <div class="nx-w-150">
                        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['avatarHtml'] ?? ''))
                </div>
                <div class="nx-grow nx-cell-5 word-break-all">
                        <br />
                        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['commentHtml'] ?? ''))
                </div>
            </div>
        </div>
    @endforeach
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom))
@endif
@endsection
