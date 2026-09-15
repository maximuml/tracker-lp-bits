@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open(($lang_staff['text_firstline_support'] ?? 'Firstline Support') . '<font class=small> - [<a class=altlink href=contactstaff.php><b>' . ($lang_staff['text_apply_for_it'] ?? 'Apply') . '</b></a>]</font>', false, 10, '100%', 'left')))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_staff['text_firstline_support_note'] ?? ''))
<br /><br />
<table data-nx="data" width=100% cellspacing=0 align=center>
    <tr>
        <td class=embedded><b>{{ $lang_staff['text_username'] ?? 'Username' }}</b></td>
        <td class=embedded align=center><b>{{ $lang_staff['text_country'] ?? 'Country' }}</b></td>
        <td class=embedded align=center><b>{{ $lang_staff['text_online_or_offline'] ?? 'Online/Offline' }}</b></td>
        <td class=embedded align=center><b>{{ $lang_staff['text_contact'] ?? 'Contact' }}</b></td>
        <td class=embedded align=center><b>{{ $lang_staff['text_language'] ?? 'Language' }}</b></td>
        <td class=embedded><b>{{ $lang_staff['text_support_for'] ?? 'Support for' }}</b></td>
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

@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open(($lang_staff['text_movie_critics'] ?? 'Movie Critics') . '<font class=small> - [<a class=altlink href=contactstaff.php><b>' . ($lang_staff['text_apply_for_it'] ?? 'Apply') . '</b></a>]</font>', false, 10, '100%', 'left')))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_staff['text_movie_critics_note'] ?? ''))
<br /><br />
<table data-nx="data" width=100% cellspacing=0 align=center>
    <tr>
        <td class=embedded><b>{{ $lang_staff['text_username'] ?? 'Username' }}</b></td>
        <td class=embedded align=center><b>{{ $lang_staff['text_country'] ?? 'Country' }}</b></td>
        <td class=embedded align=center><b>{{ $lang_staff['text_online_or_offline'] ?? 'Online/Offline' }}</b></td>
        <td class=embedded align=center><b>{{ $lang_staff['text_contact'] ?? 'Contact' }}</b></td>
        <td class=embedded><b>{{ $lang_staff['text_responsible_for'] ?? 'Responsible for' }}</b></td>
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

@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open(($lang_staff['text_forum_moderators'] ?? 'Forum Moderators') . '<font class=small> - [<a class=altlink href=contactstaff.php><b>' . ($lang_staff['text_apply_for_it'] ?? 'Apply') . '</b></a>]</font>', false, 10, '100%', 'left')))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_staff['text_forum_moderators_note'] ?? ''))
<br /><br />
<table data-nx="data" width=100% cellspacing=0 align=center>
    <tr>
        <td class=embedded><b>{{ $lang_staff['text_username'] ?? 'Username' }}</b></td>
        <td class=embedded align=center><b>{{ $lang_staff['text_country'] ?? 'Country' }}</b></td>
        <td class=embedded align=center><b>{{ $lang_staff['text_online_or_offline'] ?? 'Online/Offline' }}</b></td>
        <td class=embedded align=center><b>{{ $lang_staff['text_contact'] ?? 'Contact' }}</b></td>
        <td class=embedded><b>{{ $lang_staff['text_forums'] ?? 'Forums' }}</b></td>
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

@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open(($lang_staff['text_general_staff'] ?? 'General Staff') . '<font class=small> - [<a class=altlink href=contactstaff.php><b>' . ($lang_staff['text_apply_for_it'] ?? 'Apply') . '</b></a>]</font>', false, 10, '100%', 'left')))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_staff['text_general_staff_note'] ?? ''))
<br /><br />
<table data-nx="data" width=100% cellspacing=0 align=center>
    @foreach ($staffRows as $row)
        @if (isset($row['header']))
            @if (! $loop->first)<tr height=15><td class=embedded colspan=5 align=right>&nbsp;</td></tr>@endif
            <tr height=15><td class=embedded colspan=5 align=right>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['class_name'] ?? ''))</td></tr>
            <tr>
                <td class=embedded><b>{{ $lang_staff['text_username'] ?? 'Username' }}</b></td>
                <td class=embedded align=center><b>{{ $lang_staff['text_country'] ?? 'Country' }}</b></td>
                <td class=embedded align=center><b>{{ $lang_staff['text_online_or_offline'] ?? 'Online/Offline' }}</b></td>
                <td class=embedded align=center><b>{{ $lang_staff['text_contact'] ?? 'Contact' }}</b></td>
                <td class=embedded><b>{{ $lang_staff['text_duties'] ?? 'Duties' }}</b></td>
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

@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open($lang_staff['text_vip'] ?? 'VIP', false, 10, '100%', 'left')))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(sprintf($lang_staff['text_vip_note'] ?? '%s', $siteName)))
<br /><br />
<table data-nx="data" width=100% cellspacing=0 align=center>
    <tr>
        <td class=embedded><b>{{ $lang_staff['text_username'] ?? 'Username' }}</b></td>
        <td class=embedded align=center><b>{{ $lang_staff['text_country'] ?? 'Country' }}</b></td>
        <td class=embedded align=center><b>{{ $lang_staff['text_online_or_offline'] ?? 'Online/Offline' }}</b></td>
        <td class=embedded align=center><b>{{ $lang_staff['text_contact'] ?? 'Contact' }}</b></td>
        <td class=embedded><b>{{ $lang_staff['text_reason'] ?? 'Reason' }}</b></td>
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
