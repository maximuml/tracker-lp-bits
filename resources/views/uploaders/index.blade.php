@extends('layouts.legacy')

@section('title', __('legacy/uploaders.text_uploaders'))

@section('content')
<div>
<h1 align="center">{{ __('legacy/uploaders.text_uploaders')}} - {{ date('Y-m', $timeStart) }}</h1>

<div>
<form method="get" action="?">
<span>
{{ __('legacy/uploaders.text_select_month')}}
<select name="year">{{ $yearOptions }}</select>
&nbsp;&nbsp;
<select name="month">{{ $monthOptions }}</select>
&nbsp;&nbsp;
<input type="submit" value="{{ __('legacy/uploaders.submit_go')}}" />
</span>
</form>
</div>

@if (empty($rows))
<p align="center">{{ __('legacy/uploaders.text_no_uploaders_yet')}}</p>
@else
<div>
<table data-nx="data" border="1" cellspacing="0" cellpadding="5" align="center" width="97%">
<tr>
    <td class="colhead">{{ __('legacy/uploaders.col_username')}}</td>
    <td class="colhead">{{ __('legacy/uploaders.col_torrents_size')}}</td>
    <td class="colhead">{{ __('legacy/uploaders.col_torrents_num')}}</td>
    <td class="colhead">{{ __('legacy/uploaders.col_last_upload_time')}}</td>
    <td class="colhead">{{ __('legacy/uploaders.col_last_upload')}}</td>
</tr>
@foreach ($rows as $row)
<tr>
    <td class="colfollow">{{ $row['usernameHtml'] }}</td>
    <td class="colfollow">{{ $row['sizeFormatted'] }}</td>
    <td class="colfollow">{{ $row['torrent_count'] }}</td>
    <td class="colfollow">{{ $row['lastAddedFormatted'] }}</td>
    <td class="colfollow">{{ $row['lastTorrentHtml'] }}</td>
</tr>
@endforeach
</table>
</div>
<div>
<span id="order"><span class="big"><b>{{ __('legacy/uploaders.text_order_by')}}</b></span>
<span id="orderlist" class="dropmenu nx-hidden"><ul>
<li><a href="?year={{ (int) $year }}&amp;month={{ (int) $month }}&amp;order=username">{{ __('legacy/uploaders.text_username')}}</a></li>
<li><a href="?year={{ (int) $year }}&amp;month={{ (int) $month }}&amp;order=torrent_size">{{ __('legacy/uploaders.text_torrent_size')}}</a></li>
<li><a href="?year={{ (int) $year }}&amp;month={{ (int) $month }}&amp;order=torrent_count">{{ __('legacy/uploaders.text_torrent_num')}}</a></li>
</ul>
</span>
</span>
</div>
@endif
</div>
@endsection
