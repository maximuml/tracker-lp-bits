@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title)

@section('content')
<h1 class="text-center">{{ $title }}<a href="/userdetails?id={{ (int) $uid }}"><b>&nbsp;{{ $username }}</b></a></h1>

<div>
    <form id="filterForm" action="{{ $requestUri }}" method="get">
        <input type="hidden" name="uid" value="{{ (int) $uid }}" />
        <span>{{ $categoryText }}:</span>
        <select name="category">
            @foreach ($categoryOptionList as $o)
                <option value="{{ $o['value'] }}" @if ($o['selected']) selected @endif>{{ $o['label'] }}</option>
            @endforeach
        </select>
        &nbsp;&nbsp;
        <span>{{ $businessTypeText }}:</span>
        <select name="business_type">
            <option value="0">-{{ $textSelectOnePlease }}-</option>
            @foreach ($businessTypeOptionList as $o)
                <option value="{{ $o['value'] }}" @if ($o['selected']) selected @endif>{{ $o['label'] }}</option>
            @endforeach
        </select>
        &nbsp;&nbsp;
        <input type="submit" value="{{ $submitText }}">
        <input type="button" id="reset" value="{{ $resetText }}">
    </form>
</div>

<x-data-table :caption="$title" captionHidden id='bonus-log-table'>
<x-slot:head>
<thead>
<tr>
    <th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ $columnBusinessTypeLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ $columnOldTotalLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ $columnValueLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ $columnNewTotalLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ $columnCommentLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ $columnCreatedAtLabel }}</th>
</tr>
</thead>
</x-slot:head>

@foreach ($rows as $row)
<tr>
    <td class='align-top px-2.5 py-1.5 whitespace-nowrap'>{{ $row['businessTypeText'] }}</td>
    <td class='align-top px-2.5 py-1.5 whitespace-nowrap'>{{ $row['old_formatted'] }}</td>
    <td class='align-top px-2.5 py-1.5 whitespace-nowrap'>{{ $row['value_formatted'] }}</td>
    <td class='align-top px-2.5 py-1.5 whitespace-nowrap'>{{ $row['new_formatted'] }}</td>
    <td class='align-top px-2.5 py-1.5 whitespace-nowrap'>{{ $row['comment'] }}</td>
    <td class='align-top px-2.5 py-1.5 whitespace-nowrap'>{{ $row['created_at'] }}</td>
</tr>
@endforeach
</x-data-table>
{{ $pagerbottom ?? '' }}
@endsection
