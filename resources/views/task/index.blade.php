@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title)

@section('content')
<h1>{{ $title }}</h1>

<x-data-table :caption="$title" captionHidden>
<x-slot:head><thead>
<tr>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ $columnNameLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ $columnIndexLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ $columnBeginTimeLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ $columnEndTimeLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ $columnTargetUserLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ $columnSuccessRewardLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ $columnFailDeductLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ $columnClaimedUserCountLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ $columnDescLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ $columnClaimLabel }}</th>
</tr>
</thead></x-slot:head>
@foreach ($rows as $row)
<tr>
    <td class="whitespace-nowrap"><strong>{{ $row['name'] }}</strong></td>
    <td class="whitespace-nowrap">{{ $row['indexFormatted'] }}</td>
    <td>{{ $row['beginForUser'] }}</td>
    <td>{{ $row['endForUser'] }}</td>
    <td>{{ $row['filterFormatted'] }}</td>
    <td>{{ $row['rewardFormatted'] }}</td>
    <td>{{ $row['deductFormatted'] }}</td>
    <td>{{ $row['claimedCount'] }}</td>
    <td>{{ $row['description'] }}</td>
    <td><input type="button" class="{{ $row['claimable'] ? 'claim' : '' }}" data-id="{{ $row['id'] }}" value="{{ $row['claimText'] }}"@unless($row['claimable']) disabled @endunless></td>
</tr>
@endforeach
</x-data-table>

{{ $pagerbottom ?? '' }}
@endsection
