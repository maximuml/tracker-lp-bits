@extends('layouts.legacy')

@section('title', __('legacy/viewsnatches.head_snatch_detail'))

@section('content')
<h1 align=center>{{ __('legacy/viewsnatches.text_snatch_detail_for') }}<a href=details.php?id={{ (int) $id }}><b>{{ $torrentName }}</b></a></h1>
@if ($count)
<p align=center>{{ __('legacy/viewsnatches.text_users_top_finished_recently') }}</p>
<table data-nx="data" border=1 cellspacing=0 cellpadding=5 align=center width=940>
<tr><td class=colhead align=center>{{ __('legacy/viewsnatches.col_username') }}</td>@if ($canViewConfidential)<td class=colhead align=center>{{ __('legacy/viewsnatches.col_ip') }}</td>@endif<td class=colhead align=center>{{ __('legacy/viewsnatches.col_uploaded') }}/{{ __('legacy/viewsnatches.col_downloaded') }}</td><td class=colhead align=center>{{ __('legacy/viewsnatches.col_ratio') }}</td><td class=colhead align=center>{{ __('legacy/viewsnatches.col_se_time') }}</td><td class=colhead align=center>{{ __('legacy/viewsnatches.col_le_time') }}</td><td class=colhead align=center>{{ __('legacy/viewsnatches.col_when_completed') }}</td><td class=colhead align=center>{{ __('legacy/viewsnatches.col_last_action') }}</td><td class=colhead align=center>{{ __('legacy/viewsnatches.col_report_user') }}</td></tr>
@foreach ($rows as $row)
<tr @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['highlight']))><td class=rowfollow align=center>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['usernameHtml']))</td>@if ($canViewConfidential)<td class=rowfollow align=center><span class='nowrap'>{{ $row['ip'] }}</span></td>@endif<td class=rowfollow align=center>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['trafficHtml']))</td><td class=rowfollow align=center>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['ratioHtml']))</td><td class=rowfollow align=center>{{ $row['seedtime'] }}</td><td class=rowfollow align=center>{{ $row['leechtime'] }}</td><td class=rowfollow align=center>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['completedAtHtml']))</td><td class=rowfollow align=center>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['lastActionHtml']))</td><td class=rowfollow align=center style='padding: 0px'>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['reportHtml']))</td></tr>
@endforeach
</table>
{{ $pagerbottom ?? '' }}
@else
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::stdMessage(__('legacy/viewsnatches.std_sorry'), __('legacy/viewsnatches.std_no_snatched_users'), false)))
@endif
@endsection
