@extends('layouts.legacy')

@section('title', $title)

@section('content')
<h1 style="text-align: center">{{ $title }}</h1>

<table border="1" cellspacing="0" cellpadding="5" width="100%">
<thead>
<tr>
    <td class="colhead">{{ $columnNameLabel }}</td>
    <td class="colhead">{{ $columnIndexLabel }}</td>
    <td class="colhead">{{ $columnBeginTimeLabel }}</td>
    <td class="colhead">{{ $columnEndTimeLabel }}</td>
    <td class="colhead">{{ $columnTargetUserLabel }}</td>
    <td class="colhead">{{ $columnSuccessRewardLabel }}</td>
    <td class="colhead">{{ $columnFailDeductLabel }}</td>
    <td class="colhead">{{ $columnClaimedUserCountLabel }}</td>
    <td class="colhead">{{ $columnDescLabel }}</td>
    <td class="colhead">{{ $columnClaimLabel }}</td>
</tr>
</thead>
<tbody>
@foreach ($rows as $row)
<tr>
    <td class="nowrap"><strong>{{ $row['name'] }}</strong></td>
    <td class="nowrap">{{ $row['indexFormatted'] }}</td>
    <td>{{ $row['beginForUser'] }}</td>
    <td>{{ $row['endForUser'] }}</td>
    <td>{{ $row['filterFormatted'] }}</td>
    <td>{{ $row['rewardFormatted'] }}</td>
    <td>{{ $row['deductFormatted'] }}</td>
    <td>{{ $row['claimedCount'] }}</td>
    <td>{{ $row['description'] }}</td>
    <td>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['claimActionHtml']))</td>
</tr>
@endforeach
</tbody>
</table>

@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
@endsection
