@extends('layouts.legacy')

@section('title', $lang_uploaders['text_uploaders'] ?? 'Uploaders')

@section('content')
<div style="width: 940px">
<h1 align="center">{{ $lang_uploaders['text_uploaders'] ?? 'Uploaders' }} - {{ date('Y-m', $timeStart) }}</h1>

<div>
<form method="get" action="?">
<span>
{{ $lang_uploaders['text_select_month'] ?? 'Select month:' }}
<select name="year">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($yearOptions))</select>
&nbsp;&nbsp;
<select name="month">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($monthOptions))</select>
&nbsp;&nbsp;
<input type="submit" value="{{ $lang_uploaders['submit_go'] ?? 'Go' }}" />
</span>
</form>
</div>

@if (empty($rows))
<p align="center">{{ $lang_uploaders['text_no_uploaders_yet'] ?? 'No uploaders yet.' }}</p>
@else
<div style="margin-top: 8px">
<table data-nx="data" border="1" cellspacing="0" cellpadding="5" align="center" width="97%">
<tr>
    <td class="colhead">{{ $lang_uploaders['col_username'] ?? 'Username' }}</td>
    <td class="colhead">{{ $lang_uploaders['col_torrents_size'] ?? 'Torrents size' }}</td>
    <td class="colhead">{{ $lang_uploaders['col_torrents_num'] ?? 'Torrents num' }}</td>
    <td class="colhead">{{ $lang_uploaders['col_last_upload_time'] ?? 'Last upload time' }}</td>
    <td class="colhead">{{ $lang_uploaders['col_last_upload'] ?? 'Last upload' }}</td>
</tr>
@foreach ($rows as $row)
<tr>
    <td class="colfollow">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['usernameHtml']))</td>
    <td class="colfollow">{{ $row['sizeFormatted'] }}</td>
    <td class="colfollow">{{ $row['torrent_count'] }}</td>
    <td class="colfollow">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['lastAddedFormatted']))</td>
    <td class="colfollow">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['lastTorrentHtml']))</td>
</tr>
@endforeach
</table>
</div>
<div style="margin-top: 8px; margin-bottom: 8px;">
<span id="order" style="cursor:pointer"><span style="cursor: pointer;" class="big"><b>{{ $lang_uploaders['text_order_by'] ?? 'Order by' }}</b></span>
<span id="orderlist" class="dropmenu nx-hidden"><ul>
<li><a href="?year={{ (int) $year }}&amp;month={{ (int) $month }}&amp;order=username">{{ $lang_uploaders['text_username'] ?? 'Username' }}</a></li>
<li><a href="?year={{ (int) $year }}&amp;month={{ (int) $month }}&amp;order=torrent_size">{{ $lang_uploaders['text_torrent_size'] ?? 'Torrent size' }}</a></li>
<li><a href="?year={{ (int) $year }}&amp;month={{ (int) $month }}&amp;order=torrent_count">{{ $lang_uploaders['text_torrent_num'] ?? 'Torrent num' }}</a></li>
</ul>
</span>
</span>
</div>
@endif
</div>
@endsection
