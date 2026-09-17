@extends('layouts.legacy')

@section('title', $title)

@section('content')
@if ($mode === 'list')
    @if ($page === null)
        <x-frame :caption="__('legacy/complains.pending_complaints')" :center="false">
            @if (! empty($pendingRows))
                <table data-nx="data" width="100%">
                <tr>
                    <td class="colhead">{{ __('legacy/complains.th_complain_at') ?? 'Added' }}</td>
                    <td class="colhead">{{ __('legacy/complains.th_complain_account') ?? 'Account' }}</td>
                    <td class="colhead">{{ __('legacy/complains.th_action_view') ?? 'View' }}</td>
                </tr>
                @foreach ($pendingRows as $row)
                    <tr>
                        <td class="rowfollow">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Time::format($row['added'] ?? '')))</td>
                        <td class="rowfollow">{{ $row['email'] ?? '' }}</td>
                        <td class="rowfollow"><a href="?action=view&id={{ $row['uuid'] ?? '' }}" class="faqlink">{{ __('legacy/complains.th_action_view') ?? 'View' }}</a></td>
                    </tr>
                @endforeach
                </table>
            @else
                {{ __('legacy/complains.no_pending_complaints') ?? 'No pending complaints.' }}
            @endif
        </x-frame>
    @endif

    <x-frame :caption="__('legacy/complains.complaints_processed')" :center="false">
        @if (! empty($processedRows))
            @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagertop ?? ''))
            <table data-nx="data" width="100%">
            <tr>
                <td class="colhead">{{ __('legacy/complains.th_complain_at') ?? 'Added' }}</td>
                <td class="colhead">{{ __('legacy/complains.th_complain_account') ?? 'Account' }}</td>
                <td class="colhead">{{ __('legacy/complains.th_action_view') ?? 'View' }}</td>
            </tr>
            @foreach ($processedRows as $row)
                <tr>
                    <td class="rowfollow">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Time::format($row['added'] ?? '')))</td>
                    <td class="rowfollow">{{ $row['email'] ?? '' }}</td>
                    <td class="rowfollow"><a href="?action=view&id={{ $row['uuid'] ?? '' }}" class="faqlink">{{ __('legacy/complains.th_action_view') ?? 'View' }}</a></td>
                </tr>
            @endforeach
            </table>
            @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
        @else
            {{ __('legacy/complains.no_complaints_have_been_processed') ?? 'No complaints have been processed.' }}
        @endif
    </x-frame>

@elseif ($mode === 'view')
    @if (! $isLogin)
        <x-frame :caption="__('legacy/complains.text_created_title')" :center="false">
            <p style="font-weight: bold; color: red">{{ __('legacy/complains.text_created_note') ?? '' }}</p>
        </x-frame>
    @endif

    <x-frame :caption="__('legacy/complains.text_new_body')" :center="false">
        {{ __('legacy/complains.text_added') ?? 'Added' }}：@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Time::format($complain['added'] ?? '')))<br />{{ __('legacy/complains.text_new_email') ?? 'Email' }} {{ $complain['email'] ?? '' }}
        @if ($isAdmin)
            @if (! empty($user))
                [<a href="userdetails.php?id={{ (int) ($user['id'] ?? 0) }}" class="faqlink" target="_blank">{{ $user['username'] ?? '' }}</a>]
                [<a href="user-ban-log.php?q={{ urlencode((string) ($user['username'] ?? '')) }}" class="faqlink" target="_blank">{{ __('legacy/complains.text_view_band_log') ?? 'View ban log' }}</a>]
            @else
                [<a href="usersearch.php?em={{ urlencode((string) ($complain['email'] ?? '')) }}" class="faqlink" target="_blank">{{ __('legacy/complains.text_search_account') ?? 'Search account' }}</a>]
            @endif
            <br />IP: {{ $complain['ip'] ?? '' }}
        @endif
        <hr />@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Format::formatComment($complain['body'] ?? '')))
    </x-frame>

    <x-frame :caption="__('legacy/complains.text_replies')" :center="false">
        @if (! empty($replyRows))
            @foreach ($replyRows as $row)
                <b>{{ (int) ($row['userid'] ?? 0) !== 0 ? ($replyUserMap[(int) $row['userid']] ?? \App\Support\UserDisplay::plainUsername((int) $row['userid'])) : (__('legacy/complains.text_complainer')) }} @ @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Time::format($row['added'] ?? '')))
                @if ($isAdmin)
                    ({{ $row['ip'] ?? '' }})
                @endif
                : </b>
                @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Format::formatComment($row['body'] ?? '')))<hr />
            @endforeach
        @else
            <p align="center">{{ __('legacy/complains.text_no_replies') ?? 'No replies.' }}</p>
        @endif
    </x-frame>

    @if (! empty($complain['answered']) && (int) $complain['answered'] !== 0)
        <p align="center">{{ __('legacy/complains.text_closed') ?? 'This complain has been closed.' }}</p>
    @else
        <br /><br /><div class="nx-box nx-center"><b>{{ __('legacy/complains.text_reply') ?? 'Reply' }}</b><br /><br /><form id="reply" method="post" action=""><input type="hidden" name="action" value="reply" /><input type="hidden" name="id" value="{{ (int) ($complain['id'] ?? 0) }}" /><br />
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($replyBoxHtml))
        </form></div>
    @endif

    @if ($isAdmin)
        <form action="" method="post" style="text-align: center; margin-top: 2em"><input type="hidden" name="action" value="{{ ! empty($complain['answered']) ? 'unanswered' : 'answered' }}" /><input type="hidden" name="id" value="{{ (int) ($complain['id'] ?? 0) }}" /><button>{{ ! empty($complain['answered']) ? (__('legacy/complains.text_unanswer_it')) : (__('legacy/complains.text_answer_it')) }}</button></form>
    @endif

@else
    <h2>{{ __('legacy/complains.text_new_complain') ?? 'New complain' }}</h2>
    <form action="" method="post">
        <input type="hidden" name="action" value="new" />
        <div class="nx-fgrid nx-fgrid--flat">
            <div class="nx-fhead">{{ __('legacy/complains.text_new_email') ?? 'Email' }}</div><div class="nx-fcell"><input type="email" name="email" style="width: min(100%, 420px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" autocomplete="email" /></div>
            <div class="nx-fhead">{{ __('legacy/complains.text_new_body') ?? 'Body' }}</div><div class="nx-fcell"><textarea name="body" style="width: min(100%, 420px); min-width: 180px; border: 1px solid gray; box-sizing: border-box; height: 250px; resize: vertical;" placeholder="{{ __('legacy/complains.text_new_body_placeholder') ?? '' }}"></textarea></div>
            @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($captchaHtml))
            <div class="nx-ffull nx-center"><input type="submit" value="{{ __('legacy/complains.text_new_submit') ?? 'Submit' }}" class="btn" /></div>
        </div>
    </form>
@endif
@endsection
