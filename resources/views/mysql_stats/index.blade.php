@extends('layouts.app')

@section('title', __('mysqlstats.page_title'))

@section('content')
<x-page :title="'Mysql Server Status'" width="list">

<div class="nx-box">
{{ $vm->serverRunningText }}
</div>

<ul>
    <li>
        <b>{{ __('mysqlstats.server_traffic') }}</b> These tables show the network traffic statistics of this MySQL server since its startup
        <br />
        <div class="flex items-start overflow-x-auto" tabindex="0">
                    <x-data-table caption="Traffic" captionHidden><x-slot:head><thead><tr>
                            <th colspan="2" scope="colgroup">&nbsp;Traffic&nbsp;</th>
                            <th scope="col">&nbsp;&nbsp;Per Hour&nbsp;</th>
                        </tr></thead></x-slot:head>
                        <tr>
                            <td>&nbsp;Received&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->receivedTotal }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->receivedPerHour }}&nbsp;</td>
                        </tr>
                        <tr>
                            <td>&nbsp;Sent&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->sentTotal }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->sentPerHour }}&nbsp;</td>
                        </tr>
                        <tr class="nx-mysql-total">
                            <td>&nbsp;Total&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->totalBytesTotal }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->totalBytesPerHour }}&nbsp;</td>
                        </tr>
                    </x-data-table>
                    <x-data-table caption="Connections" captionHidden><x-slot:head><thead><tr>
                            <th colspan="2" scope="colgroup">&nbsp;Connections&nbsp;</th>
                            <th scope="col">&nbsp;&oslash;&nbsp;Per Hour&nbsp;</th>
                            <th scope="col">&nbsp;%&nbsp;</th>
                        </tr></thead></x-slot:head>
                        <tr>
                            <td>&nbsp;Failed Attempts&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->abortedConnects }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->abortedConnectsPerHour }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->abortedConnectsPct }}&nbsp;</td>
                        </tr>
                        <tr>
                            <td>&nbsp;Aborted Clients&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->abortedClients }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->abortedClientsPerHour }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->abortedClientsPct }}&nbsp;</td>
                        </tr>
                        <tr class="nx-mysql-total">
                            <td>&nbsp;Total&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->connectionsTotal }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->connectionsPerHour }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ number_format(100, 2, '.', ',') }}&nbsp;%&nbsp;</td>
                        </tr>
                    </x-data-table>
        </div>
        <br />
    </li>
    <li>
        <b>{{ __('mysqlstats.query_stats') }}</b> Since it's start up, {{ $vm->questionsTotal }} queries have been sent to the server.
        <div>
                    <br />
                    <x-data-table caption="Query Statistics" captionHidden><x-slot:head><thead><tr>
                            <th scope="col">&nbsp;Total&nbsp;</th>
                            <th scope="col">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>
                            <th scope="col">&nbsp;&oslash;&nbsp;Per&nbsp;Minute&nbsp;</th>
                            <th scope="col">&nbsp;&oslash;&nbsp;Per&nbsp;Second&nbsp;</th>
                        </tr></thead></x-slot:head>
                        <tr>
                            <td class="text-right">&nbsp;{{ $vm->questionsTotal }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->questionsPerHour }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->questionsPerMinute }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $vm->questionsPerSecond }}&nbsp;</td>
                        </tr>
                    </x-data-table>
            <div class="flex items-start">
@foreach ($vm->queryStatColumns as $column)
                    <x-data-table caption="Query Type" captionHidden><x-slot:head><thead><tr>
                            <th colspan="2" scope="colgroup">&nbsp;Query&nbsp;Type&nbsp;</th>
                            <th scope="col">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>
                            <th scope="col">&nbsp;%&nbsp;</th>
                        </tr></thead></x-slot:head>
@foreach ($column as $row)
                        <tr>
                            <td>&nbsp;{{ $row['name'] }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $row['value'] }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $row['perHour'] }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $row['pct'] }}&nbsp;%&nbsp;</td>
                        </tr>
@endforeach
                    </x-data-table>
@endforeach
            </div>
        </div>
    </li>
@if ($vm->hasServerStatus)
    <li>
    <br />
        <b>{{ __('mysqlstats.more_vars') }}</b><br />
        <div class="flex items-start">
@foreach ($vm->statusColumns as $column)
                    <x-data-table caption="More status variables" captionHidden><x-slot:head><thead><tr>
                            <th scope="col">&nbsp;Variable&nbsp;</th>
                            <th scope="col">&nbsp;Value&nbsp;</th>
                        </tr></thead></x-slot:head>
@foreach ($column as $row)
                        <tr>
                            <td>&nbsp;{{ $row['name'] }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $row['value'] }}&nbsp;</td>
                        </tr>
@endforeach
                    </x-data-table>
@endforeach
        </div>
    </li>
@endif
</ul>
</x-page>
@endsection
