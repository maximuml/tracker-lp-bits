@extends('layouts.app')

@section('title', 'User ban log')

@section('content')
<x-page :title="'User ban log'" width="list">
    <form id="filterForm" action="{{ $serverRequestUri }}" method="get">
        <input id="q" type="text" name="q" value="{{ $q }}" placeholder="username" aria-label="username">
        <input type="submit" class="nx-btn nx-btn--primary">
        <input type="reset" class="nx-btn js-filter-reset">
    </form>
<x-data-table caption="User ban log" captionHidden>
    <x-slot:head><thead><tr>
        @foreach ($header as $label)
            <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ $label }}</th>
        @endforeach
    </tr></thead></x-slot:head>
        @foreach ($rows as $row)
            <tr>
                @foreach ($header as $key => $label)
                    <td class="">{{ $row[$key] ?? '' }}</td>
                @endforeach
            </tr>
        @endforeach
</x-data-table>
{{ ($paginationBottom ?? '') }}
</x-page>
@endsection
