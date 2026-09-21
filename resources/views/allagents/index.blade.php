@extends('layouts.app', ['chromeVariant' => 'legacy', 'shell' => 'bare'])

@section('title', 'All Clients')

@section('content')
<table data-nx="data"><caption class="nx-sr-only">All Clients</caption>
    <tr><th class="colhead" scope="col">Client</th><th class="colhead" scope="col">Counts</th></tr>
    @foreach ($agents as $row)
        <tr><td>{{ ((array) $row)['agent'] ?? '' }}</td><td>{{ ((array) $row)['counts'] ?? '' }}</td></tr>
    @endforeach
</table>
@endsection
