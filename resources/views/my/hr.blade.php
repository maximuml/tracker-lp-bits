@extends('layouts.legacy')

@section('title', ($userInfo->username ?? '') . ' - H&R')

@section('content')
<h1>{{ ($userInfo->username ?? '') . ' - H&R' }}</h1>
<p>{{ $headerFilters ?? '' }}</p>
<form id="filterForm" action="{{ $requestUri ?? '' }}" method="get">
    <input id="q" type="text" name="q" value="{{ $q ?? '' }}" placeholder="{{ __('legacy/myhr.th_hr_id')}}">
    <input type="submit">
    <input type="reset" class="js-filter-reset">
</form>
<table data-nx="data" width='100%' id='hr-table'>
<tr>
    <th class="colhead" align="center" scope="col">{{ __('legacy/myhr.th_hr_id')}}</th>
    <th class="colhead" align="center" scope="col">{{ __('legacy/myhr.th_torrent_name')}}</th>
    <th class="colhead" align="center" scope="col">{{ __('legacy/myhr.th_uploaded')}}</th>
    <th class="colhead" align="center" scope="col">{{ __('legacy/myhr.th_downloaded')}}</th>
    <th class="colhead" align="center" scope="col">{{ __('legacy/myhr.th_share_ratio')}}</th>
    <th class="colhead" align="center" scope="col">{{ __('legacy/myhr.th_seed_time_required')}}</th>
    <th class="colhead" align="center" scope="col">{{ __('legacy/myhr.th_completed_at')}}</th>
    <th class="colhead" align="center" scope="col">{{ __('legacy/myhr.th_ttl')}}</th>
    <th class="colhead" align="center" scope="col">{{ __('legacy/myhr.th_comment')}}</th>
    <th class="colhead" align="center" scope="col">{{ __('legacy/functions.std_action')}}</th>
</tr>
@if (! empty($rescount))
    @foreach ($list as $row)
    <tr>
        <td class='rowfollow nowrap' align='center'>{{ $row->id }}</td>
        <td class='rowfollow' align='left'><a href='details.php?id={{ $row->torrent_id }}'>{{ optional($row->torrent)->name }}</a></td>
        <td class='rowfollow nowrap' align='center'>{{ \App\Support\Format::size($row->snatch->uploaded) }}</td>
        <td class='rowfollow nowrap' align='center'>{{ \App\Support\Format::size($row->snatch->downloaded) }}</td>
        <td class='rowfollow nowrap' align='center'>{{ \App\Support\Ratio::hr($row->snatch->uploaded, $row->snatch->downloaded) }}</td>
        <td class='rowfollow nowrap' align='center'>{{ $row->seedTimeRequired }}</td>
        <td class='rowfollow nowrap' align='center'>{{ \App\Support\Time::formatDateTime($row->snatch->completedat) }}</td>
        <td class='rowfollow nowrap' align='center'>{{ $row->inspectTimeLeft }}</td>
        <td class='rowfollow nowrap' align='left'>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(nl2br(e(trim((string) $row->comment)))))</td>
        <td class="rowfollow nowrap" align="center">
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
