@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', 'Mysql Server Status')

@section('content')
<section class="nx-idx-card">
<h1 class="text-center">
    Mysql Server Status
</h1>

<div class="nx-box">
{{ $serverRunningText }}
</div>

<ul>
    <li>
        <b>Server traffic:</b> These tables show the network traffic statistics of this MySQL server since its startup
        <br />
        <div class="flex items-start">
                    <table data-nx="data" id="torrenttable"><caption class="nx-sr-only">Traffic</caption>
                        <tr>
                            <th colspan="2" scope="colgroup">&nbsp;Traffic&nbsp;</th>
                            <th scope="col">&nbsp;&nbsp;Per Hour&nbsp;</th>
                        </tr>
                        <tr>
                            <td>&nbsp;Received&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $receivedTotal }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $receivedPerHour }}&nbsp;</td>
                        </tr>
                        <tr>
                            <td>&nbsp;Sent&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $sentTotal }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $sentPerHour }}&nbsp;</td>
                        </tr>
                        <tr class="nx-mysql-total">
                            <td>&nbsp;Total&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $totalBytesTotal }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $totalBytesPerHour }}&nbsp;</td>
                        </tr>
                    </table>
                    <table data-nx="data" id="torrenttable"><caption class="nx-sr-only">Connections</caption>
                        <tr>
                            <th colspan="2" scope="colgroup">&nbsp;Connections&nbsp;</th>
                            <th scope="col">&nbsp;&oslash;&nbsp;Per Hour&nbsp;</th>
                            <th scope="col">&nbsp;%&nbsp;</th>
                        </tr>
                        <tr>
                            <td>&nbsp;Failed Attempts&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $abortedConnects }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $abortedConnectsPerHour }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $abortedConnectsPct }}&nbsp;</td>
                        </tr>
                        <tr>
                            <td>&nbsp;Aborted Clients&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $abortedClients }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $abortedClientsPerHour }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $abortedClientsPct }}&nbsp;</td>
                        </tr>
                        <tr class="nx-mysql-total">
                            <td>&nbsp;Total&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $connectionsTotal }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $connectionsPerHour }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ number_format(100, 2, '.', ',') }}&nbsp;%&nbsp;</td>
                        </tr>
                    </table>
        </div>
    </li>
    <br />
    <li>
        <b>Query Statistics:</b> Since it's start up, {{ $questionsTotal }} queries have been sent to the server.
        <div>
                    <br />
                    <table data-nx="data" id="torrenttable"><caption class="nx-sr-only">Query Statistics</caption>
                        <tr>
                            <th scope="col">&nbsp;Total&nbsp;</th>
                            <th scope="col">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>
                            <th scope="col">&nbsp;&oslash;&nbsp;Per&nbsp;Minute&nbsp;</th>
                            <th scope="col">&nbsp;&oslash;&nbsp;Per&nbsp;Second&nbsp;</th>
                        </tr>
                        <tr>
                            <td class="text-right">&nbsp;{{ $questionsTotal }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $questionsPerHour }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $questionsPerMinute }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $questionsPerSecond }}&nbsp;</td>
                        </tr>
                    </table>
            <div class="flex items-start">
@foreach ($queryStatColumns as $column)
                    <table data-nx="data" id="torrenttable"><caption class="nx-sr-only">Query Type</caption>
                        <tr>
                            <th colspan="2" scope="colgroup">&nbsp;Query&nbsp;Type&nbsp;</th>
                            <th scope="col">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>
                            <th scope="col">&nbsp;%&nbsp;</th>
                        </tr>
@foreach ($column as $row)
                        <tr>
                            <td>&nbsp;{{ $row['name'] }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $row['value'] }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $row['perHour'] }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $row['pct'] }}&nbsp;%&nbsp;</td>
                        </tr>
@endforeach
                    </table>
@endforeach
            </div>
        </div>
    </li>
@if ($hasServerStatus)
    <br />
    <li>
        <b>More status variables</b><br />
        <div class="flex items-start">
@foreach ($statusColumns as $column)
                    <table data-nx="data" id="torrenttable"><caption class="nx-sr-only">More status variables</caption>
                        <tr>
                            <th scope="col">&nbsp;Variable&nbsp;</th>
                            <th scope="col">&nbsp;Value&nbsp;</th>
                        </tr>
@foreach ($column as $row)
                        <tr>
                            <td>&nbsp;{{ $row['name'] }}&nbsp;</td>
                            <td class="text-right">&nbsp;{{ $row['value'] }}&nbsp;</td>
                        </tr>
@endforeach
                    </table>
@endforeach
        </div>
    </li>
@endif
</ul>
</section>
@endsection
