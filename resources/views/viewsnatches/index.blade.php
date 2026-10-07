@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('viewsnatches.head_snatch_detail'))

@section('content')
<h1 class="text-center">{{ __('viewsnatches.text_snatch_detail_for') }}<a href=/web/details/{{ (int) $id }}><b>{{ $torrentName }}</b></a></h1>
@if ($count)
<p class="text-center">{{ __('viewsnatches.text_users_top_finished_recently') }}</p>
<x-data-table :caption="__('viewsnatches.text_snatch_detail_for').' '.$torrentName" :caption-hidden="true">
    <x-slot:head>
        <thead>
            <tr><th scope="col">{{ __('viewsnatches.col_username') }}</th>@if ($canViewConfidential)<th scope="col">{{ __('viewsnatches.col_ip') }}</th>@endif<th scope="col">{{ __('viewsnatches.col_uploaded') }}/{{ __('viewsnatches.col_downloaded') }}</th><th scope="col">{{ __('viewsnatches.col_ratio') }}</th><th scope="col">{{ __('viewsnatches.col_se_time') }}</th><th scope="col">{{ __('viewsnatches.col_le_time') }}</th><th scope="col">{{ __('viewsnatches.col_when_completed') }}</th><th scope="col">{{ __('viewsnatches.col_last_action') }}</th><th scope="col">{{ __('viewsnatches.col_report_user') }}</th></tr>
        </thead>
    </x-slot:head>
    @foreach ($rows as $row)
        <tr{{ $row['highlight'] ? ' class="bg-[#00A527]"' : '' }}><td class="text-center">@if ($row['anonymous']){{ __('viewsnatches.text_anonymous') }}@if ($row['revealName'])<br />({{ $row['name'] }})@endif
@else{{ $row['name'] }}@endif</td>@if ($canViewConfidential)<td class="text-center"><span class='whitespace-nowrap'>{{ $row['ip'] }}</span></td>@endif<td class="text-center">{{ $row['trafficUp'] }}<br />{{ $row['trafficDown'] }}</td><td class="text-center">@if ($row['ratioClass'])<span class="{{ $row['ratioClass'] }}">{{ $row['ratioText'] }}</span>@else{{ $row['ratioText'] }}@endif</td><td class="text-center">{{ $row['seedtime'] }}</td><td class="text-center">{{ $row['leechtime'] }}</td><td class="text-center">{{ $row['completedAt'] }}</td><td class="text-center">{{ $row['lastAction'] }}</td><td class="text-center">@if ($row['reportLinked'])<a href=/web/report?user={{ $row['reportUserId'] }}>@endif<img class="f_report" src="pic/trans.gif" alt="Report" title="{{ __('viewsnatches.title_report') }}" />@if ($row['reportLinked'])</a>@endif</td></tr>
    @endforeach
</x-data-table>
{{ $pagerbottom ?? '' }}
@else
<x-std-message :heading="__('viewsnatches.std_sorry')" :text="__('viewsnatches.std_no_snatched_users')" :htmlstrip="false" />
@endif
@endsection
