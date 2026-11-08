@extends('layouts.app')

@section('title', __('uploaders.text_uploaders'))

@section('content')
<x-page :title="__('uploaders.text_uploaders')" :subtitle="date('Y-m', $timeStart)" width="list">
<form method="get" action="{{ request()->getPathInfo() }}" class="nx-box mb-3 flex flex-wrap items-end gap-3 text-left">
    <div class="flex flex-col gap-1">
        <span class="mb-1 block font-bold">{{ __('uploaders.text_select_month') }}</span>
        <div class="flex items-center gap-2">
            <select name="year" aria-label="Year">@foreach ($yearOptions as $o)<option value="{{ $o['value'] }}" @if ($o['selected']) selected="selected" @endif>{{ $o['value'] }}</option>@endforeach</select>
            <select name="month" aria-label="Month">@foreach ($monthOptions as $o)<option value="{{ $o['value'] }}" @if ($o['selected']) selected="selected" @endif>{{ $o['value'] }}</option>@endforeach</select>
            <button class="nx-btn nx-btn--primary" type="submit">{{ __('uploaders.submit_go') }}</button>
        </div>
    </div>
</form>

@if (empty($rows))
<x-empty-state :title="__('uploaders.text_no_uploaders_yet')" />
@else
<x-data-table :caption="__('uploaders.text_uploaders')" captionHidden class="w-[97%] mx-auto"><x-slot:head><thead><tr>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('uploaders.col_username')}}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('uploaders.col_torrents_size')}}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('uploaders.col_torrents_num')}}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('uploaders.col_last_upload_time')}}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('uploaders.col_last_upload')}}</th>
</tr></thead></x-slot:head>
@foreach ($rows as $row)
<tr>
    <td class="nx-colfollow">{{ $row['usernameHtml'] }}</td>
    <td class="nx-colfollow">{{ $row['sizeFormatted'] }}</td>
    <td class="nx-colfollow">{{ $row['torrent_count'] }}</td>
    <td class="nx-colfollow">@if ($row['last_added'])<x-time :value="$row['last_added']" />@else{{ $naText }}@endif</td>
    <td class="nx-colfollow">@if ($row['last_name'] !== '')<a href="/web/details/{{ (int) $row['last_id'] }}">{{ $row['last_name'] }}</a>@else{{ $naText }}@endif</td>
</tr>
@endforeach
</x-data-table>
<div class="mt-3">
<span id="order"><span class="big"><b>{{ __('uploaders.text_order_by')}}</b></span>
<span id="orderlist" class="dropmenu nx-hidden"><ul>
<li><a href="{{ request()->getPathInfo() }}?year={{ (int) $year }}&amp;month={{ (int) $month }}&amp;order=username">{{ __('uploaders.text_username')}}</a></li>
<li><a href="{{ request()->getPathInfo() }}?year={{ (int) $year }}&amp;month={{ (int) $month }}&amp;order=torrent_size">{{ __('uploaders.text_torrent_size')}}</a></li>
<li><a href="{{ request()->getPathInfo() }}?year={{ (int) $year }}&amp;month={{ (int) $month }}&amp;order=torrent_count">{{ __('uploaders.text_torrent_num')}}</a></li>
</ul>
</span>
</span>
</div>
@endif
</x-page>
@endsection
