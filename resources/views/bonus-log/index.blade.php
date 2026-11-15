@extends('layouts.app')

@section('title', $vm->title)

@section('content')
<x-page :title="$vm->title" :subtitle="$vm->username" width="list">
<x-slot:actions><a class="nx-btn nx-btn--secondary" href="/userdetails?id={{ (int) $vm->uid }}">{{ __('functions.text_profile') }}</a></x-slot:actions>
<form id="filterForm" action="{{ $vm->requestUri }}" method="get" class="nx-box mb-3 flex flex-wrap items-end gap-3 text-left">
    <input type="hidden" name="uid" value="{{ (int) $vm->uid }}" />
    <div class="flex flex-col gap-1">
        <label for="nx-bonuslog-category">{{ $vm->categoryText }}</label>
        <select id="nx-bonuslog-category" name="category">
            @foreach ($vm->categoryOptionList as $o)
                <option value="{{ $o['value'] }}" @if ($o['selected']) selected @endif>{{ $o['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex flex-col gap-1">
        <label for="nx-bonuslog-biztype">{{ $vm->businessTypeText }}</label>
        <select id="nx-bonuslog-biztype" name="business_type">
            <option value="0">-{{ $vm->textSelectOnePlease }}-</option>
            @foreach ($vm->businessTypeOptionList as $o)
                <option value="{{ $o['value'] }}" @if ($o['selected']) selected @endif>{{ $o['label'] }}</option>
            @endforeach
        </select>
    </div>
    <button class="nx-btn nx-btn--primary" type="submit">{{ $vm->submitText }}</button>
    <button class="nx-btn" type="button" id="reset">{{ $vm->resetText }}</button>
</form>

<x-data-table :caption="$vm->title" captionHidden id='bonus-log-table'>
<x-slot:head>
<thead>
<tr>
    <th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ $vm->columnBusinessTypeLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ $vm->columnOldTotalLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ $vm->columnValueLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ $vm->columnNewTotalLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ $vm->columnCommentLabel }}</th>
    <th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ $vm->columnCreatedAtLabel }}</th>
</tr>
</thead>
</x-slot:head>

@foreach ($vm->rows as $row)
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
{{ $vm->pagerbottom ?? '' }}
</x-page>
@endsection
