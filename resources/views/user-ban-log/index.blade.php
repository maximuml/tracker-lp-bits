@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', 'User ban log')

@section('content')
<div>
    <h1>User ban log</h1>
    <form id="filterForm" action="{{ $serverRequestUri }}" method="get">
        <input id="q" type="text" name="q" value="{{ $q }}" placeholder="username">
        <input type="submit">
        <input type="reset" class="js-filter-reset">
    </form>
</div>

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
@endsection
