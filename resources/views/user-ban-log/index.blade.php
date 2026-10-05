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

<table data-nx="data">
    <caption class="nx-sr-only">User ban log</caption>
    <thead><tr>
        @foreach ($header as $label)
            <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ $label }}</th>
        @endforeach
    </tr></thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                @foreach ($header as $key => $label)
                    <td class="">{{ $row[$key] ?? '' }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
{{ ($paginationBottom ?? '') }}
@endsection
