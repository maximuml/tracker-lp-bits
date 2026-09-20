@extends('layouts.legacy')

@section('title', __('legacy/viewsnatches.head_snatch_detail'))

@section('content')
<h1 align=center>{{ __('legacy/viewsnatches.text_snatch_detail_for') }}<a href=details.php?id={{ (int) $id }}><b>{{ $torrentName }}</b></a></h1>
@if ($count)
<p align=center>{{ __('legacy/viewsnatches.text_users_top_finished_recently') }}</p>
<table data-nx="data" border=1 cellspacing=0 cellpadding=5 align=center width=940>
<tr><td class=colhead align=center>{{ __('legacy/viewsnatches.col_username') }}</td>@if ($canViewConfidential)<td class=colhead align=center>{{ __('legacy/viewsnatches.col_ip') }}</td>@endif<td class=colhead align=center>{{ __('legacy/viewsnatches.col_uploaded') }}/{{ __('legacy/viewsnatches.col_downloaded') }}</td><td class=colhead align=center>{{ __('legacy/viewsnatches.col_ratio') }}</td><td class=colhead align=center>{{ __('legacy/viewsnatches.col_se_time') }}</td><td class=colhead align=center>{{ __('legacy/viewsnatches.col_le_time') }}</td><td class=colhead align=center>{{ __('legacy/viewsnatches.col_when_completed') }}</td><td class=colhead align=center>{{ __('legacy/viewsnatches.col_last_action') }}</td><td class=colhead align=center>{{ __('legacy/viewsnatches.col_report_user') }}</td></tr>
@foreach ($rows as $row)
<tr{{ $row['highlight'] ? ' bgcolor=#00A527' : '' }}><td class=rowfollow align=center>@if ($row['anonymous']){{ __('legacy/viewsnatches.text_anonymous') }}@if ($row['revealName'])<br />({{ $row['name'] }})@endif
@else{{ $row['name'] }}@endif</td>@if ($canViewConfidential)<td class=rowfollow align=center><span class='nowrap'>{{ $row['ip'] }}</span></td>@endif<td class=rowfollow align=center>{{ $row['trafficUp'] }}<br />{{ $row['trafficDown'] }}</td><td class=rowfollow align=center>@if ($row['ratioClass'])<span class="{{ $row['ratioClass'] }}">{{ $row['ratioText'] }}</span>@else{{ $row['ratioText'] }}@endif</td><td class=rowfollow align=center>{{ $row['seedtime'] }}</td><td class=rowfollow align=center>{{ $row['leechtime'] }}</td><td class=rowfollow align=center>{{ $row['completedAt'] }}</td><td class=rowfollow align=center>{{ $row['lastAction'] }}</td><td class=rowfollow align=center>@if ($row['reportLinked'])<a href=report.php?user={{ $row['reportUserId'] }}>@endif<img class="f_report" src="pic/trans.gif" alt="Report" title="{{ __('legacy/viewsnatches.title_report') }}" />@if ($row['reportLinked'])</a>@endif</td></tr>
@endforeach
</table>
{{ $pagerbottom ?? '' }}
@else
<x-std-message :heading="__('legacy/viewsnatches.std_sorry')" :text="__('legacy/viewsnatches.std_no_snatched_users')" :htmlstrip="false" />
@endif
@endsection
