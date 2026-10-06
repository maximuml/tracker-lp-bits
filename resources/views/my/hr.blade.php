@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', ($userInfo->username ?? '') . ' - H&R')

@section('content')
<h1>{{ ($userInfo->username ?? '') . ' - H&R' }}</h1>
<p>@foreach(($headerFilters ?? []) as $headerFilter)<a href="{{ request()->getPathInfo() }}?{{ $headerFilter['query'] }}" class="{{ $headerFilter['active'] ? 'faqlink' : '' }}"><b>{{ $headerFilter['text'] }}</b></a>@if(! $loop->last) | @endif@endforeach</p>
<form id="filterForm" action="{{ $requestUri ?? '' }}" method="get">
    <input id="q" type="text" name="q" value="{{ $q ?? '' }}" placeholder="{{ __('legacy/myhr.th_hr_id')}}">
    <input type="submit">
    <input type="reset" class="js-filter-reset">
</form>
<x-data-table id="hr-table" :caption="($userInfo->username ?? '') . ' - H&R'" :caption-hidden="true" :headers="[
    __('legacy/myhr.th_hr_id'),
    __('legacy/myhr.th_torrent_name'),
    __('legacy/myhr.th_uploaded'),
    __('legacy/myhr.th_downloaded'),
    __('legacy/myhr.th_share_ratio'),
    __('legacy/myhr.th_seed_time_required'),
    __('legacy/myhr.th_completed_at'),
    __('legacy/myhr.th_ttl'),
    __('legacy/myhr.th_comment'),
    __('legacy/functions.std_action'),
]">
@if (! empty($rescount))
    @foreach ($list as $row)
    <tr>
        <td class="whitespace-nowrap text-center">{{ $row->id }}</td>
        <td><a href='/web/details/{{ $row->torrent_id }}'>{{ optional($row->torrent)->name }}</a></td>
        <td class="whitespace-nowrap text-center">{{ \App\Support\Format::size($row->snatch->uploaded) }}</td>
        <td class="whitespace-nowrap text-center">{{ \App\Support\Format::size($row->snatch->downloaded) }}</td>
        <td class="whitespace-nowrap text-center">{{ \App\Support\Ratio::hr($row->snatch->uploaded, $row->snatch->downloaded) }}</td>
        <td class="whitespace-nowrap text-center">{{ $row->seedTimeRequired }}</td>
        <td class="whitespace-nowrap text-center">{{ \App\Support\Time::formatDateTime($row->snatch->completedat) }}</td>
        <td class="whitespace-nowrap text-center">{{ $row->inspectTimeLeft }}</td>
        <td class="whitespace-nowrap"><x-nl2br :text="(string) $row->comment" /></td>
        <td class="whitespace-nowrap text-center">
            @if ($row->uid == ($CURUSER['id'] ?? 0) && in_array($row->status, \App\Models\HitAndRun::CAN_PARDON_STATUS))
                <input class="remove-hr" type="button" value="{{ __('legacy/myhr.action_remove')}}" data-id="{{ $row->id }}">
            @endif
        </td>
    </tr>
    @endforeach
@endif
</x-data-table>
{{ $pagerbottom ?? '' }}
@endsection
