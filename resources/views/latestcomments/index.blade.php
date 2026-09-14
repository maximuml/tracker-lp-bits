@extends('layouts.legacy')

@section('title', $title ?? ($lang_functions['text_latest_comments'] ?? 'Latest Comments'))

@section('content')
@if ($count == 0)
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::stdMessage($lang_functions['text_sorry'] ?? 'Sorry', $lang_functions['text_no_comments'] ?? 'No comments yet.', false)))
@else
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagertop))
    <h1 align="center">{{ $lang_functions['text_latest_comments'] ?? 'Latest Comments' }}</h1>
    @foreach ($rows as $row)
        <div style="margin-top: 8pt; margin-bottom: 8pt;">
            <table id="cid{{ $row['id'] }}" border="0" cellspacing="0" cellpadding="0" width="100%">
                <tr>
                    <td class="embedded" width="99%">
                        #{{ $row['id'] }}&nbsp;&nbsp;
                        <font color="gray">{{ $lang_functions['text_by'] ?? 'by' }}</font>
                        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['usernameHtml'] ?? ''))
                        &nbsp;&nbsp;<font color="gray">{{ $lang_functions['text_at'] ?? 'at' }}</font>
                        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['timeHtml'] ?? ''))
                        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['parentLinkHtml'] ?? ''))
                    </td>
                </tr>
            </table>
            <table class="main" width="100%" border="0" cellspacing="0" cellpadding="5">
                <tr>
                    <td class="rowfollow" width="150" valign="top" style="padding: 0px;">
                        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['avatarHtml'] ?? ''))
                    </td>
                    <td class="rowfollow word-break-all" valign="top">
                        <br />
                        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['commentHtml'] ?? ''))
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom))
@endif
@endsection
