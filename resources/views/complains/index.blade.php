@extends('layouts.legacy')

@section('title', $title)

@section('content')
@if ($mode === 'list')
    @if ($page === null)
        <x-frame :caption="$langComplains['pending_complaints'] ?? 'Pending complaints'" :center="false">
            @if (! empty($pendingRows))
                <table width="100%">
                <tr>
                    <td class="colhead">{{ $langComplains['th_complain_at'] ?? 'Added' }}</td>
                    <td class="colhead">{{ $langComplains['th_complain_account'] ?? 'Account' }}</td>
                    <td class="colhead">{{ $langComplains['th_action_view'] ?? 'View' }}</td>
                </tr>
                @foreach ($pendingRows as $row)
                    <tr>
                        <td class="rowfollow">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Time::format($row['added'] ?? '')))</td>
                        <td class="rowfollow">{{ $row['email'] ?? '' }}</td>
                        <td class="rowfollow"><a href="?action=view&id={{ $row['uuid'] ?? '' }}" class="faqlink">{{ $langComplains['th_action_view'] ?? 'View' }}</a></td>
                    </tr>
                @endforeach
                </table>
            @else
                {{ $langComplains['no_pending_complaints'] ?? 'No pending complaints.' }}
            @endif
        </x-frame>
    @endif

    <x-frame :caption="$langComplains['complaints_processed'] ?? 'Processed complaints'" :center="false">
        @if (! empty($processedRows))
            @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagertop ?? ''))
            <table width="100%">
            <tr>
                <td class="colhead">{{ $langComplains['th_complain_at'] ?? 'Added' }}</td>
                <td class="colhead">{{ $langComplains['th_complain_account'] ?? 'Account' }}</td>
                <td class="colhead">{{ $langComplains['th_action_view'] ?? 'View' }}</td>
            </tr>
            @foreach ($processedRows as $row)
                <tr>
                    <td class="rowfollow">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Time::format($row['added'] ?? '')))</td>
                    <td class="rowfollow">{{ $row['email'] ?? '' }}</td>
                    <td class="rowfollow"><a href="?action=view&id={{ $row['uuid'] ?? '' }}" class="faqlink">{{ $langComplains['th_action_view'] ?? 'View' }}</a></td>
                </tr>
            @endforeach
            </table>
            @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
        @else
            {{ $langComplains['no_complaints_have_been_processed'] ?? 'No complaints have been processed.' }}
        @endif
    </x-frame>

@elseif ($mode === 'view')
    @if (! $isLogin)
        <x-frame :caption="$langComplains['text_created_title'] ?? 'Created'" :center="false">
            <p style="font-weight: bold; color: red">{{ $langComplains['text_created_note'] ?? '' }}</p>
        </x-frame>
    @endif

    <x-frame :caption="$langComplains['text_new_body'] ?? 'Body'" :center="false">
        {{ $langComplains['text_added'] ?? 'Added' }}：@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Time::format($complain['added'] ?? '')))<br />{{ $langComplains['text_new_email'] ?? 'Email' }} {{ $complain['email'] ?? '' }}
        @if ($isAdmin)
            @if (! empty($user))
                [<a href="userdetails.php?id={{ (int) ($user['id'] ?? 0) }}" class="faqlink" target="_blank">{{ $user['username'] ?? '' }}</a>]
                [<a href="user-ban-log.php?q={{ urlencode((string) ($user['username'] ?? '')) }}" class="faqlink" target="_blank">{{ $langComplains['text_view_band_log'] ?? 'View ban log' }}</a>]
            @else
                [<a href="usersearch.php?em={{ urlencode((string) ($complain['email'] ?? '')) }}" class="faqlink" target="_blank">{{ $langComplains['text_search_account'] ?? 'Search account' }}</a>]
            @endif
            <br />IP: {{ $complain['ip'] ?? '' }}
        @endif
        <hr />@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Format::formatComment($complain['body'] ?? '')))
    </x-frame>

    <x-frame :caption="$langComplains['text_replies'] ?? 'Replies'" :center="false">
        @if (! empty($replyRows))
            @foreach ($replyRows as $row)
                <b>{{ (int) ($row['userid'] ?? 0) !== 0 ? ($replyUserMap[(int) $row['userid']] ?? \App\Support\UserDisplay::plainUsername((int) $row['userid'])) : ($langComplains['text_complainer'] ?? 'Complainer') }} @ @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Time::format($row['added'] ?? '')))
                @if ($isAdmin)
                    ({{ $row['ip'] ?? '' }})
                @endif
                : </b>
                @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Format::formatComment($row['body'] ?? '')))<hr />
            @endforeach
        @else
            <p align="center">{{ $langComplains['text_no_replies'] ?? 'No replies.' }}</p>
        @endif
    </x-frame>

    @if (! empty($complain['answered']) && (int) $complain['answered'] !== 0)
        <p align="center">{{ $langComplains['text_closed'] ?? 'This complain has been closed.' }}</p>
    @else
        <br /><br /><table style="border:1px solid #000000;" align="center"><tr><td class="text" align="center"><b>{{ $langComplains['text_reply'] ?? 'Reply' }}</b><br /><br /><form id="reply" method="post" action=""><input type="hidden" name="action" value="reply" /><input type="hidden" name="id" value="{{ (int) ($complain['id'] ?? 0) }}" /><br />
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($replyBoxHtml))
        </form></td></tr></table>
    @endif

    @if ($isAdmin)
        <form action="" method="post" style="text-align: center; margin-top: 2em"><input type="hidden" name="action" value="{{ ! empty($complain['answered']) ? 'unanswered' : 'answered' }}" /><input type="hidden" name="id" value="{{ (int) ($complain['id'] ?? 0) }}" /><button>{{ ! empty($complain['answered']) ? ($langComplains['text_unanswer_it'] ?? 'Reopen') : ($langComplains['text_answer_it'] ?? 'Close') }}</button></form>
    @endif

@else
    <h2>{{ $langComplains['text_new_complain'] ?? 'New complain' }}</h2>
    <form action="" method="post">
        <input type="hidden" name="action" value="new" />
        <table border="0" cellpadding="5">
            <tr><td class="rowhead">{{ $langComplains['text_new_email'] ?? 'Email' }}</td><td class="rowfollow" align="left"><input type="email" name="email" style="width: min(100%, 420px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" autocomplete="email" /></td></tr>
            <tr><td class="rowhead">{{ $langComplains['text_new_body'] ?? 'Body' }}</td><td class="rowfollow" align="left"><textarea name="body" style="width: min(100%, 420px); min-width: 180px; border: 1px solid gray; box-sizing: border-box; height: 250px; resize: vertical;" placeholder="{{ $langComplains['text_new_body_placeholder'] ?? '' }}"></textarea></td></tr>
            @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($captchaHtml))
            <tr><td class="toolbox" colspan="2" align="center"><input type="submit" value="{{ $langComplains['text_new_submit'] ?? 'Submit' }}" class="btn" /></td></tr>
        </table>
    </form>
@endif
@endsection
