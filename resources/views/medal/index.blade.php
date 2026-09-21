@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title)

@section('content')
<h1>{{ $title }}</h1>

<div>
    <form id="filterForm" action="" method="get">
        <input id="q" type="text" name="q" value="{{ $q }}" placeholder="username">
        <input type="submit">
        <input type="reset" class="js-filter-reset">
    </form>
</div>

<table data-nx="data"><caption class="nx-sr-only">{{ $title }}</caption>
<thead>
<tr>
    <th class="colhead" scope="col">ID</th>
    <th class="colhead" scope="col">{{ $columnImageLargeLabel }}</th>
    <th class="colhead" scope="col">{{ $columnDescriptionLabel }}</th>
    <th class="colhead" scope="col">{{ $columnSaleBeginEndTimeLabel }}</th>
    <th class="colhead" scope="col">{{ $columnDurationLabel }}</th>
    <th class="colhead" scope="col">{{ $columnBonusAdditionLabel }}</th>
    <th class="colhead" scope="col">{{ $columnPriceLabel }}</th>
    <th class="colhead" scope="col">{{ $columnInventoryLabel }}</th>
    <th class="colhead" scope="col">{{ $columnBuyLabel }}</th>
    <th class="colhead" scope="col">{{ $columnGiftLabel }}</th>
</tr>
</thead>
<tbody>
@foreach ($rows as $row)
<tr>
    <td>{{ (int) $row['id'] }}</td>
    <td><img src="{{ $row['image_large'] }}" class="preview" /></td>
    <td><h1>{{ $row['name'] }}</h1>{{ $row['description'] }}</td>
    <td>{{ $row['sale_begin_time'] }} ~<br>{{ $row['sale_end_time'] }}</td>
    <td>{{ $row['durationText'] }}</td>
    <td>{{ $row['bonus_addition_factor'] }}%</td>
    <td>{{ number_format((float) $row['price']) }}</td>
    <td>{{ $row['inventory'] }}</td>
    <td>{{ $row['buy_action'] }}</td>
    <td>{{ $row['gift_action'] }}</td>
</tr>
@endforeach
</tbody>
</table>

{{ $pagerbottom ?? '' }}
@endsection
