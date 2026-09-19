@extends('layouts.legacy')

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

<table data-nx="data" border="1" cellspacing="0" cellpadding="5" width="100%">
<thead>
<tr>
    <td class="colhead">ID</td>
    <td class="colhead">{{ $columnImageLargeLabel }}</td>
    <td class="colhead">{{ $columnDescriptionLabel }}</td>
    <td class="colhead">{{ $columnSaleBeginEndTimeLabel }}</td>
    <td class="colhead">{{ $columnDurationLabel }}</td>
    <td class="colhead">{{ $columnBonusAdditionLabel }}</td>
    <td class="colhead">{{ $columnPriceLabel }}</td>
    <td class="colhead">{{ $columnInventoryLabel }}</td>
    <td class="colhead">{{ $columnBuyLabel }}</td>
    <td class="colhead">{{ $columnGiftLabel }}</td>
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
