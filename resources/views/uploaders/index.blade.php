@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('legacy/uploaders.text_uploaders'))

@section('content')
<div>
<h1 class="text-center">{{ __('legacy/uploaders.text_uploaders')}} - {{ date('Y-m', $timeStart) }}</h1>

<div>
<form method="get" action="{{ request()->getPathInfo() }}">
<span>
{{ __('legacy/uploaders.text_select_month')}}
<select name="year">@foreach ($yearOptions as $o)<option value="{{ $o['value'] }}" @if ($o['selected']) selected="selected" @endif>{{ $o['value'] }}</option>@endforeach</select>
&nbsp;&nbsp;
<select name="month">@foreach ($monthOptions as $o)<option value="{{ $o['value'] }}" @if ($o['selected']) selected="selected" @endif>{{ $o['value'] }}</option>@endforeach</select>
&nbsp;&nbsp;
<input type="submit" value="{{ __('legacy/uploaders.submit_go')}}" />
</span>
</form>
</div>

@if (empty($rows))
<p class="text-center">{{ __('legacy/uploaders.text_no_uploaders_yet')}}</p>
@else
<div>
<x-data-table :caption="__('legacy/uploaders.text_uploaders')" captionHidden class="w-[97%] mx-auto"><x-slot:head><thead><tr>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/uploaders.col_username')}}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/uploaders.col_torrents_size')}}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/uploaders.col_torrents_num')}}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/uploaders.col_last_upload_time')}}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/uploaders.col_last_upload')}}</th>
</tr></thead></x-slot:head>
@foreach ($rows as $row)
<tr>
    <td class="colfollow">{{ $row['usernameHtml'] }}</td>
    <td class="colfollow">{{ $row['sizeFormatted'] }}</td>
    <td class="colfollow">{{ $row['torrent_count'] }}</td>
    <td class="colfollow">@if ($row['last_added'])<x-time :value="$row['last_added']" />@else{{ $naText }}@endif</td>
    <td class="colfollow">@if ($row['last_name'] !== '')<a href="/web/details/{{ (int) $row['last_id'] }}">{{ $row['last_name'] }}</a>@else{{ $naText }}@endif</td>
</tr>
@endforeach
</x-data-table>
</div>
<div>
<span id="order"><span class="big"><b>{{ __('legacy/uploaders.text_order_by')}}</b></span>
<span id="orderlist" class="dropmenu nx-hidden"><ul>
<li><a href="{{ request()->getPathInfo() }}?year={{ (int) $year }}&amp;month={{ (int) $month }}&amp;order=username">{{ __('legacy/uploaders.text_username')}}</a></li>
<li><a href="{{ request()->getPathInfo() }}?year={{ (int) $year }}&amp;month={{ (int) $month }}&amp;order=torrent_size">{{ __('legacy/uploaders.text_torrent_size')}}</a></li>
<li><a href="{{ request()->getPathInfo() }}?year={{ (int) $year }}&amp;month={{ (int) $month }}&amp;order=torrent_count">{{ __('legacy/uploaders.text_torrent_num')}}</a></li>
</ul>
</span>
</span>
</div>
@endif
</div>
@endsection
