@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', ($userInfo->username ?? '') . ' - H&R')

@section('content')
<h1>{{ ($userInfo->username ?? '') . ' - H&R' }}</h1>
<p>@foreach(($headerFilters ?? []) as $headerFilter)<a href="?{{ $headerFilter['query'] }}" class="{{ $headerFilter['active'] ? 'faqlink' : '' }}"><b>{{ $headerFilter['text'] }}</b></a>@if(! $loop->last) | @endif@endforeach</p>
<form id="filterForm" action="{{ $requestUri ?? '' }}" method="get">
    <input id="q" type="text" name="q" value="{{ $q ?? '' }}" placeholder="{{ __('legacy/myhr.th_hr_id')}}">
    <input type="submit">
    <input type="reset" class="js-filter-reset">
</form>
<table data-nx="data" id='hr-table'><caption class="nx-sr-only">{{ ($userInfo->username ?? '') . ' - H&R' }}</caption>
<tr>
    <th class="colhead" scope="col">{{ __('legacy/myhr.th_hr_id')}}</th>
    <th class="colhead" scope="col">{{ __('legacy/myhr.th_torrent_name')}}</th>
    <th class="colhead" scope="col">{{ __('legacy/myhr.th_uploaded')}}</th>
    <th class="colhead" scope="col">{{ __('legacy/myhr.th_downloaded')}}</th>
    <th class="colhead" scope="col">{{ __('legacy/myhr.th_share_ratio')}}</th>
    <th class="colhead" scope="col">{{ __('legacy/myhr.th_seed_time_required')}}</th>
    <th class="colhead" scope="col">{{ __('legacy/myhr.th_completed_at')}}</th>
    <th class="colhead" scope="col">{{ __('legacy/myhr.th_ttl')}}</th>
    <th class="colhead" scope="col">{{ __('legacy/myhr.th_comment')}}</th>
    <th class="colhead" scope="col">{{ __('legacy/functions.std_action')}}</th>
</tr>
@if (! empty($rescount))
    @foreach ($list as $row)
    <tr>
        <td class='rowfollow nowrap nx-center'>{{ $row->id }}</td>
        <td class='rowfollow'><a href='details.php?id={{ $row->torrent_id }}'>{{ optional($row->torrent)->name }}</a></td>
        <td class='rowfollow nowrap nx-center'>{{ \App\Support\Format::size($row->snatch->uploaded) }}</td>
        <td class='rowfollow nowrap nx-center'>{{ \App\Support\Format::size($row->snatch->downloaded) }}</td>
        <td class='rowfollow nowrap nx-center'>{{ \App\Support\Ratio::hr($row->snatch->uploaded, $row->snatch->downloaded) }}</td>
        <td class='rowfollow nowrap nx-center'>{{ $row->seedTimeRequired }}</td>
        <td class='rowfollow nowrap nx-center'>{{ \App\Support\Time::formatDateTime($row->snatch->completedat) }}</td>
        <td class='rowfollow nowrap nx-center'>{{ $row->inspectTimeLeft }}</td>
        <td class='rowfollow nowrap'><x-nl2br :text="(string) $row->comment" /></td>
        <td class="rowfollow nowrap nx-center">
            @if ($row->uid == ($CURUSER['id'] ?? 0) && in_array($row->status, \App\Models\HitAndRun::CAN_PARDON_STATUS))
                <input class="remove-hr" type="button" value="{{ __('legacy/myhr.action_remove')}}" data-id="{{ $row->id }}">
            @endif
        </td>
    </tr>
    @endforeach
@endif
</table>
{{ $pagerbottom ?? '' }}
@endsection
