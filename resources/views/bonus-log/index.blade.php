@extends('layouts.app')

@section('title', $title)

@section('content')
<x-page :title="$title" :subtitle="$username" width="list">
<x-slot:actions><a class="nx-btn nx-btn--secondary" href="/userdetails?id={{ (int) $uid }}">{{ __('functions.text_profile') }}</a></x-slot:actions>
<form id="filterForm" action="{{ $requestUri }}" method="get" class="nx-box mb-3 flex flex-wrap items-end gap-3 text-left">
    <input type="hidden" name="uid" value="{{ (int) $uid }}" />
    <div class="flex flex-col gap-1">
        <label for="nx-bonuslog-category">{{ $categoryText }}</label>
        <select id="nx-bonuslog-category" name="category">
            @foreach ($categoryOptionList as $o)
                <option value="{{ $o['value'] }}" @if ($o['selected']) selected @endif>{{ $o['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex flex-col gap-1">
        <label for="nx-bonuslog-biztype">{{ $businessTypeText }}</label>
        <select id="nx-bonuslog-biztype" name="business_type">
            <option value="0">-{{ $textSelectOnePlease }}-</option>
            @foreach ($businessTypeOptionList as $o)
                <option value="{{ $o['value'] }}" @if ($o['selected']) selected @endif>{{ $o['label'] }}</option>
            @endforeach
        </select>
    </div>
    <button class="nx-btn nx-btn--primary" type="submit">{{ $submitText }}</button>
    <button class="nx-btn" type="button" id="reset">{{ $resetText }}</button>
</form>

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
</x-page>
@endsection
