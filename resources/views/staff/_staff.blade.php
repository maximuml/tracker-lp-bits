@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open((__('legacy/staff.text_firstline_support')) . '<font class=small> - [<a class=altlink href=contactstaff.php><b>' . (__('legacy/staff.text_apply_for_it')) . '</b></a>]</font>', false, 10, '100%', 'left')))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/staff.text_firstline_support_note')))
<br /><br />
<table data-nx="data" width=100% cellspacing=0 align=center>
    <tr>
        <td class=embedded><b>{{ __('legacy/staff.text_username')}}</b></td>
        <td class=embedded align=center><b>{{ __('legacy/staff.text_country')}}</b></td>
        <td class=embedded align=center><b>{{ __('legacy/staff.text_online_or_offline')}}</b></td>
        <td class=embedded align=center><b>{{ __('legacy/staff.text_contact')}}</b></td>
        <td class=embedded align=center><b>{{ __('legacy/staff.text_language')}}</b></td>
        <td class=embedded><b>{{ __('legacy/staff.text_support_for')}}</b></td>
    </tr>
    <tr><td class=embedded colspan=6><hr color="#4040c0"></td></tr>
    @foreach ($supportRows as $row)
    <tr>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['username_html'] ?? ''))</td>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['flag_html'] ?? ''))</td>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['online_html'] ?? ''))</td>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['pm_html'] ?? ''))</td>
        <td class=embedded>{{ $row['extra'] ?? '' }}</td>
        <td class=embedded>{{ $row['extra'] ?? '' }}</td>
    </tr>
    @endforeach
</table>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))

@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open((__('legacy/staff.text_movie_critics')) . '<font class=small> - [<a class=altlink href=contactstaff.php><b>' . (__('legacy/staff.text_apply_for_it')) . '</b></a>]</font>', false, 10, '100%', 'left')))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/staff.text_movie_critics_note')))
<br /><br />
<table data-nx="data" width=100% cellspacing=0 align=center>
    <tr>
        <td class=embedded><b>{{ __('legacy/staff.text_username')}}</b></td>
        <td class=embedded align=center><b>{{ __('legacy/staff.text_country')}}</b></td>
        <td class=embedded align=center><b>{{ __('legacy/staff.text_online_or_offline')}}</b></td>
        <td class=embedded align=center><b>{{ __('legacy/staff.text_contact')}}</b></td>
        <td class=embedded><b>{{ __('legacy/staff.text_responsible_for')}}</b></td>
    </tr>
    <tr><td class=embedded colspan=5><hr color="#4040c0"></td></tr>
    @foreach ($pickerRows as $row)
    <tr>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['username_html'] ?? ''))</td>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['flag_html'] ?? ''))</td>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['online_html'] ?? ''))</td>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['pm_html'] ?? ''))</td>
        <td class=embedded>{{ $row['extra'] ?? '' }}</td>
    </tr>
    @endforeach
</table>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))

@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open((__('legacy/staff.text_forum_moderators')) . '<font class=small> - [<a class=altlink href=contactstaff.php><b>' . (__('legacy/staff.text_apply_for_it')) . '</b></a>]</font>', false, 10, '100%', 'left')))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/staff.text_forum_moderators_note')))
<br /><br />
<table data-nx="data" width=100% cellspacing=0 align=center>
    <tr>
        <td class=embedded><b>{{ __('legacy/staff.text_username')}}</b></td>
        <td class=embedded align=center><b>{{ __('legacy/staff.text_country')}}</b></td>
        <td class=embedded align=center><b>{{ __('legacy/staff.text_online_or_offline')}}</b></td>
        <td class=embedded align=center><b>{{ __('legacy/staff.text_contact')}}</b></td>
        <td class=embedded><b>{{ __('legacy/staff.text_forums')}}</b></td>
    </tr>
    <tr><td class=embedded colspan=5><hr color="#4040c0"></td></tr>
    @foreach ($forumModRows as $row)
    <tr>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['username_html'] ?? ''))</td>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['flag_html'] ?? ''))</td>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['online_html'] ?? ''))</td>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['pm_html'] ?? ''))</td>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['forums_html'] ?? ''))</td>
    </tr>
    @endforeach
</table>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))

@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open((__('legacy/staff.text_general_staff')) . '<font class=small> - [<a class=altlink href=contactstaff.php><b>' . (__('legacy/staff.text_apply_for_it')) . '</b></a>]</font>', false, 10, '100%', 'left')))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/staff.text_general_staff_note')))
<br /><br />
<table data-nx="data" width=100% cellspacing=0 align=center>
    @foreach ($staffRows as $row)
        @if (isset($row['header']))
            @if (! $loop->first)<tr height=15><td class=embedded colspan=5 align=right>&nbsp;</td></tr>@endif
            <tr height=15><td class=embedded colspan=5 align=right>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['class_name'] ?? ''))</td></tr>
            <tr>
                <td class=embedded><b>{{ __('legacy/staff.text_username')}}</b></td>
                <td class=embedded align=center><b>{{ __('legacy/staff.text_country')}}</b></td>
                <td class=embedded align=center><b>{{ __('legacy/staff.text_online_or_offline')}}</b></td>
                <td class=embedded align=center><b>{{ __('legacy/staff.text_contact')}}</b></td>
                <td class=embedded><b>{{ __('legacy/staff.text_duties')}}</b></td>
            </tr>
            <tr height=15><td class=embedded colspan=5><hr color="#4040c0"></td></tr>
        @else
            <tr>
                <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['username_html'] ?? ''))</td>
                <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['flag_html'] ?? ''))</td>
                <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['online_html'] ?? ''))</td>
                <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['pm_html'] ?? ''))</td>
                <td class=embedded>{{ $row['extra'] ?? '' }}</td>
            </tr>
        @endif
    @endforeach
</table>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))

@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open(__('legacy/staff.text_vip'), false, 10, '100%', 'left')))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(sprintf(__('legacy/staff.text_vip_note'), $siteName)))
<br /><br />
<table data-nx="data" width=100% cellspacing=0 align=center>
    <tr>
        <td class=embedded><b>{{ __('legacy/staff.text_username')}}</b></td>
        <td class=embedded align=center><b>{{ __('legacy/staff.text_country')}}</b></td>
        <td class=embedded align=center><b>{{ __('legacy/staff.text_online_or_offline')}}</b></td>
        <td class=embedded align=center><b>{{ __('legacy/staff.text_contact')}}</b></td>
        <td class=embedded><b>{{ __('legacy/staff.text_reason')}}</b></td>
    </tr>
    <tr><td class=embedded colspan=5><hr color="#4040c0"></td></tr>
    @foreach ($vipRows as $row)
    <tr>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['username_html'] ?? ''))</td>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['flag_html'] ?? ''))</td>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['online_html'] ?? ''))</td>
        <td class=embedded>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['pm_html'] ?? ''))</td>
        <td class=embedded>{{ $row['extra'] ?? '' }}</td>
    </tr>
    @endforeach
</table>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))
