@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title)

@section('content')
<h1>{{ $title }}</h1>

<table data-nx="data"><caption class="nx-sr-only">{{ $title }}</caption>
<thead>
<tr>
    <th class="colhead" scope="col">{{ $columnNameLabel }}</th>
    <th class="colhead" scope="col">{{ $columnIndexLabel }}</th>
    <th class="colhead" scope="col">{{ $columnBeginTimeLabel }}</th>
    <th class="colhead" scope="col">{{ $columnEndTimeLabel }}</th>
    <th class="colhead" scope="col">{{ $columnTargetUserLabel }}</th>
    <th class="colhead" scope="col">{{ $columnSuccessRewardLabel }}</th>
    <th class="colhead" scope="col">{{ $columnFailDeductLabel }}</th>
    <th class="colhead" scope="col">{{ $columnClaimedUserCountLabel }}</th>
    <th class="colhead" scope="col">{{ $columnDescLabel }}</th>
    <th class="colhead" scope="col">{{ $columnClaimLabel }}</th>
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
    <td>{{ $row['claimActionHtml'] }}</td>
</tr>
@endforeach
</tbody>
</table>

{{ $pagerbottom ?? '' }}
@endsection
