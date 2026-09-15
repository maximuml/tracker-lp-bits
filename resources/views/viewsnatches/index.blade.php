@extends('layouts.legacy')

@section('title', $lang_viewsnatches['head_snatch_detail'])

@section('content')
<h1 align=center>{{ $lang_viewsnatches['text_snatch_detail_for'] }}<a href=details.php?id={{ (int) $id }}><b>{{ $torrentName }}</b></a></h1>
@if ($count)
<p align=center>{{ $lang_viewsnatches['text_users_top_finished_recently'] }}</p>
<table data-nx="data" border=1 cellspacing=0 cellpadding=5 align=center width=940>
<tr><td class=colhead align=center>{{ $lang_viewsnatches['col_username'] }}</td>@if ($canViewConfidential)<td class=colhead align=center>{{ $lang_viewsnatches['col_ip'] }}</td>@endif<td class=colhead align=center>{{ $lang_viewsnatches['col_uploaded'] }}/{{ $lang_viewsnatches['col_downloaded'] }}</td><td class=colhead align=center>{{ $lang_viewsnatches['col_ratio'] }}</td><td class=colhead align=center>{{ $lang_viewsnatches['col_se_time'] }}</td><td class=colhead align=center>{{ $lang_viewsnatches['col_le_time'] }}</td><td class=colhead align=center>{{ $lang_viewsnatches['col_when_completed'] }}</td><td class=colhead align=center>{{ $lang_viewsnatches['col_last_action'] }}</td><td class=colhead align=center>{{ $lang_viewsnatches['col_report_user'] }}</td></tr>
@foreach ($rows as $row)
<tr @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['highlight']))><td class=rowfollow align=center>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['usernameHtml']))</td>@if ($canViewConfidential)<td class=rowfollow align=center><span class='nowrap'>{{ $row['ip'] }}</span></td>@endif<td class=rowfollow align=center>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['trafficHtml']))</td><td class=rowfollow align=center>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['ratioHtml']))</td><td class=rowfollow align=center>{{ $row['seedtime'] }}</td><td class=rowfollow align=center>{{ $row['leechtime'] }}</td><td class=rowfollow align=center>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['completedAtHtml']))</td><td class=rowfollow align=center>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['lastActionHtml']))</td><td class=rowfollow align=center style='padding: 0px'>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['reportHtml']))</td></tr>
@endforeach
</table>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
@else
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::stdMessage($lang_viewsnatches['std_sorry'], $lang_viewsnatches['std_no_snatched_users'], false)))
@endif
@endsection
