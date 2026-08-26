@php
$lang_complains = (array) (\App\Support\SupportContext::getGlobal('lang_complains') ?? []);
$lang_functions = (array) (\App\Support\SupportContext::getGlobal('lang_functions') ?? []);
$CURUSER = (array) (\app(\App\Support\CurrentUser::class)->get() ?? []);
$mode = (string) ($mode ?? 'compose');
$isAdmin = (bool) ($isAdmin ?? false);
$isLogin = (bool) ($isLogin ?? false);
$title = $title ?? ($lang_complains['text_complain'] ?? 'Complain');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@if ($mode === 'list')
    @php
        $pendingRows = (array) ($pendingRows ?? []);
        $processedRows = (array) ($processedRows ?? []);
        $pagertop = (string) ($pagertop ?? '');
        $pagerbottom = (string) ($pagerbottom ?? '');
        $page = $page ?? null;
    @endphp
    @if ($page === null)
        {!! \App\Support\Html::beginFrame($lang_complains['pending_complaints'] ?? 'Pending complaints') !!}
        @if (! empty($pendingRows))
            <table width="100%">
            {!! \App\Support\Html::tableRow('colhead', $lang_complains['th_complain_at'] ?? 'Added', $lang_complains['th_complain_account'] ?? 'Account', $lang_complains['th_action_view'] ?? 'View') !!}
            @foreach ($pendingRows as $row)
                @php $row = (array) $row; @endphp
                {!! \App\Support\Html::tableRow('rowfollow', \App\Support\Time::format($row['added'] ?? ''), htmlspecialchars((string) ($row['email'] ?? '')), sprintf('<a href="?action=view&id=%s" class="faqlink">%s</a>', htmlspecialchars((string) ($row['uuid'] ?? '')), $lang_complains['th_action_view'] ?? 'View')) !!}
            @endforeach
            </table>
        @else
            {{ $lang_complains['no_pending_complaints'] ?? 'No pending complaints.' }}
        @endif
        {!! \App\Support\Html::endFrame() !!}
    @endif

    {!! \App\Support\Html::beginFrame($lang_complains['complaints_processed'] ?? 'Processed complaints') !!}
    @if (! empty($processedRows))
        {{ $pagertop }}
        <table width="100%">
        {!! \App\Support\Html::tableRow('colhead', $lang_complains['th_complain_at'] ?? 'Added', $lang_complains['th_complain_account'] ?? 'Account', $lang_complains['th_action_view'] ?? 'View') !!}
        @foreach ($processedRows as $row)
            @php $row = (array) $row; @endphp
            {!! \App\Support\Html::tableRow('rowfollow', \App\Support\Time::format($row['added'] ?? ''), htmlspecialchars((string) ($row['email'] ?? '')), sprintf('<a href="?action=view&id=%s" class="faqlink">%s</a>', htmlspecialchars((string) ($row['uuid'] ?? '')), $lang_complains['th_action_view'] ?? 'View')) !!}
        @endforeach
        </table>
        {{ $pagerbottom }}
    @else
        {{ $lang_complains['no_complaints_have_been_processed'] ?? 'No complaints have been processed.' }}
    @endif
    {!! \App\Support\Html::endFrame() !!}

@elseif ($mode === 'view')
    @php
        $complain = (array) ($complain ?? []);
        $user = (array) ($user ?? []);
        $replyRows = (array) ($replyRows ?? []);
        $replyUserMap = (array) ($replyUserMap ?? []);
    @endphp
    @if (! $isLogin)
        {!! \App\Support\Html::beginFrame($lang_complains['text_created_title'] ?? 'Created') !!}
        <p style="font-weight: bold; color: red">{{ $lang_complains['text_created_note'] ?? '' }}</p>
        {!! \App\Support\Html::endFrame() !!}
    @endif

    {!! \App\Support\Html::beginFrame($lang_complains['text_new_body'] ?? 'Body') !!}
    {{ $lang_complains['text_added'] ?? 'Added' }}：{{ \App\Support\Time::format($complain['added'] ?? '') }}<br />{{ $lang_complains['text_new_email'] ?? 'Email' }} {{ htmlspecialchars((string) ($complain['email'] ?? '')) }}
    @if ($isAdmin)
        @if (! empty($user))
            [<a href="userdetails.php?id={{ (int) ($user['id'] ?? 0) }}" class="faqlink" target="_blank">{{ htmlspecialchars((string) ($user['username'] ?? '')) }}</a>]
            [<a href="user-ban-log.php?q={{ urlencode((string) ($user['username'] ?? '')) }}" class="faqlink" target="_blank">{{ $lang_complains['text_view_band_log'] ?? 'View ban log' }}</a>]
        @else
            [<a href="usersearch.php?em={{ urlencode((string) ($complain['email'] ?? '')) }}" class="faqlink" target="_blank">{{ $lang_complains['text_search_account'] ?? 'Search account' }}</a>]
        @endif
        <br />IP: {{ htmlspecialchars((string) ($complain['ip'] ?? '')) }}
    @endif
    <hr />{!! \App\Support\Format::formatComment($complain['body'] ?? '') !!}
    {!! \App\Support\Html::endFrame() !!}

    {!! \App\Support\Html::beginFrame($lang_complains['text_replies'] ?? 'Replies') !!}
    @if (! empty($replyRows))
        @foreach ($replyRows as $r)
            @php
                $row = (array) $r;
                $replyUser = (int) ($row['userid'] ?? 0) ? ($replyUserMap[(int) ($row['userid'] ?? 0)] ?? \App\Support\UserDisplay::plainUsername((int) ($row['userid'] ?? 0))) : ($lang_complains['text_complainer'] ?? 'Complainer');
            @endphp
            <b>{{ $replyUser }} @ {{ \App\Support\Time::format($row['added'] ?? '') }}
            @if ($isAdmin)
                ({{ htmlspecialchars((string) ($row['ip'] ?? '')) }})
            @endif
            : </b>
            {!! \App\Support\Format::formatComment($row['body'] ?? '') !!}<hr />
        @endforeach
    @else
        <p align="center">{{ $lang_complains['text_no_replies'] ?? 'No replies.' }}</p>
    @endif
    {!! \App\Support\Html::endFrame() !!}

    @if (! empty($complain['answered']) && (int) $complain['answered'] !== 0)
        <p align="center">{{ $lang_complains['text_closed'] ?? 'This complain has been closed.' }}</p>
    @else
        <br /><br /><table style="border:1px solid #000000;" align="center"><tr><td class="text" align="center"><b>{{ $lang_complains['text_reply'] ?? 'Reply' }}</b><br /><br /><form id="reply" method="post" action="" onsubmit="return postvalid(this);"><input type="hidden" name="action" value="reply" /><input type="hidden" name="id" value="{{ (int) ($complain['id'] ?? 0) }}" /><br />
        @php \App\Support\Html::quickReplyVoid('reply', 'body', $lang_complains['text_reply'] ?? 'Reply'); @endphp
        </form></td></tr></table>
    @endif

    @if ($isAdmin)
        <form action="" method="post" style="text-align: center; margin-top: 2em"><input type="hidden" name="action" value="{{ ! empty($complain['answered']) ? 'unanswered' : 'answered' }}" /><input type="hidden" name="id" value="{{ (int) ($complain['id'] ?? 0) }}" /><button>{{ ! empty($complain['answered']) ? ($lang_complains['text_unanswer_it'] ?? 'Reopen') : ($lang_complains['text_answer_it'] ?? 'Close') }}</button></form>
    @endif

@else
    <h2>{{ $lang_complains['text_new_complain'] ?? 'New complain' }}</h2>
    <form action="" method="post">
        <input type="hidden" name="action" value="new" />
        @php
            $inputStyle = 'style="width: min(100%, 420px); min-width: 180px; border: 1px solid gray; box-sizing: border-box"';
            $textareaStyle = 'style="width: min(100%, 420px); min-width: 180px; border: 1px solid gray; box-sizing: border-box; height: 250px; resize: vertical;"';
        @endphp
        <table border="0" cellpadding="5">
            <tr><td class="rowhead">{{ $lang_complains['text_new_email'] ?? 'Email' }}</td><td class="rowfollow" align="left"><input type="email" name="email" {{ $inputStyle }} autocomplete="email" /></td></tr>
            <tr><td class="rowhead">{{ $lang_complains['text_new_body'] ?? 'Body' }}</td><td class="rowfollow" align="left"><textarea name="body" {{ $textareaStyle }} placeholder="{{ $lang_complains['text_new_body_placeholder'] ?? '' }}"></textarea></td></tr>
            @php \App\Support\Captcha::showImageCode(); @endphp
            <tr><td class="toolbox" colspan="2" align="center"><input type="submit" value="{{ $lang_complains['text_new_submit'] ?? 'Submit' }}" class="btn" /></td></tr>
        </table>
    </form>
@endif
@endsection
