@extends('layouts.legacy')

@section('title', __('legacy/viewsnatches.head_snatch_detail'))

@section('content')
<h1 class="nx-center">{{ __('legacy/viewsnatches.text_snatch_detail_for') }}<a href=details.php?id={{ (int) $id }}><b>{{ $torrentName }}</b></a></h1>
@if ($count)
<p class="nx-center">{{ __('legacy/viewsnatches.text_users_top_finished_recently') }}</p>
<table data-nx="data"><caption class="nx-sr-only">{{ __('legacy/viewsnatches.text_snatch_detail_for') }} {{ $torrentName }}</caption>
<tr><th class="colhead" scope="col">{{ __('legacy/viewsnatches.col_username') }}</th>@if ($canViewConfidential)<th class="colhead" scope="col">{{ __('legacy/viewsnatches.col_ip') }}</th>@endif<th class="colhead" scope="col">{{ __('legacy/viewsnatches.col_uploaded') }}/{{ __('legacy/viewsnatches.col_downloaded') }}</th><th class="colhead" scope="col">{{ __('legacy/viewsnatches.col_ratio') }}</th><th class="colhead" scope="col">{{ __('legacy/viewsnatches.col_se_time') }}</th><th class="colhead" scope="col">{{ __('legacy/viewsnatches.col_le_time') }}</th><th class="colhead" scope="col">{{ __('legacy/viewsnatches.col_when_completed') }}</th><th class="colhead" scope="col">{{ __('legacy/viewsnatches.col_last_action') }}</th><th class="colhead" scope="col">{{ __('legacy/viewsnatches.col_report_user') }}</th></tr>
@foreach ($rows as $row)
<tr{{ $row['highlight'] ? ' bgcolor=#00A527' : '' }}><td class="rowfollow nx-center">@if ($row['anonymous']){{ __('legacy/viewsnatches.text_anonymous') }}@if ($row['revealName'])<br />({{ $row['name'] }})@endif
@else{{ $row['name'] }}@endif</td>@if ($canViewConfidential)<td class="rowfollow nx-center"><span class='nowrap'>{{ $row['ip'] }}</span></td>@endif<td class="rowfollow nx-center">{{ $row['trafficUp'] }}<br />{{ $row['trafficDown'] }}</td><td class="rowfollow nx-center">@if ($row['ratioClass'])<span class="{{ $row['ratioClass'] }}">{{ $row['ratioText'] }}</span>@else{{ $row['ratioText'] }}@endif</td><td class="rowfollow nx-center">{{ $row['seedtime'] }}</td><td class="rowfollow nx-center">{{ $row['leechtime'] }}</td><td class="rowfollow nx-center">{{ $row['completedAt'] }}</td><td class="rowfollow nx-center">{{ $row['lastAction'] }}</td><td class="rowfollow nx-center">@if ($row['reportLinked'])<a href=report.php?user={{ $row['reportUserId'] }}>@endif<img class="f_report" src="pic/trans.gif" alt="Report" title="{{ __('legacy/viewsnatches.title_report') }}" />@if ($row['reportLinked'])</a>@endif</td></tr>
@endforeach
</table>
{{ $pagerbottom ?? '' }}
@else
<x-std-message :heading="__('legacy/viewsnatches.std_sorry')" :text="__('legacy/viewsnatches.std_no_snatched_users')" :htmlstrip="false" />
@endif
@endsection
