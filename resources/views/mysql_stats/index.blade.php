@extends('layouts.legacy')

@section('title', 'Mysql Server Status')

@section('content')
<h1 align=center>
    Mysql Server Status
</h1>

<div class="nx-box">
{{ $serverRunningText }}
</div>

<ul>
    <li>
        <b>Server traffic:</b> These tables show the network traffic statistics of this MySQL server since its startup
        <br />
        <div class="nx-row">
                    <table data-nx="data" id="torrenttable" border="0">
                        <tr>
                            <th colspan="2" bgcolor="lightgrey">&nbsp;Traffic&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&nbsp;Per Hour&nbsp;</th>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Received&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $receivedTotal }}&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $receivedPerHour }}&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Sent&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $sentTotal }}&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $sentPerHour }}&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="lightgrey">&nbsp;Total&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;{{ $totalBytesTotal }}&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;{{ $totalBytesPerHour }}&nbsp;</td>
                        </tr>
                    </table>
                    <table data-nx="data" id="torrenttable" border="0">
                        <tr>
                            <th colspan="2" bgcolor="lightgrey">&nbsp;Connections&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per Hour&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;%&nbsp;</th>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Failed Attempts&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $abortedConnects }}&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $abortedConnectsPerHour }}&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $abortedConnectsPct }}&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Aborted Clients&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $abortedClients }}&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $abortedClientsPerHour }}&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $abortedClientsPct }}&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="lightgrey">&nbsp;Total&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;{{ $connectionsTotal }}&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;{{ $connectionsPerHour }}&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;{{ number_format(100, 2, '.', ',') }}&nbsp;%&nbsp;</td>
                        </tr>
                    </table>
        </div>
    </li>
    <br />
    <li>
        <b>Query Statistics:</b> Since it's start up, {{ $questionsTotal }} queries have been sent to the server.
        <div>
                    <br />
                    <table data-nx="data" id="torrenttable" border="0" align="right">
                        <tr>
                            <th bgcolor="lightgrey">&nbsp;Total&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Minute&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Second&nbsp;</th>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $questionsTotal }}&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $questionsPerHour }}&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $questionsPerMinute }}&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $questionsPerSecond }}&nbsp;</td>
                        </tr>
                    </table>
            <div class="nx-row">
@foreach ($queryStatColumns as $column)
                    <table data-nx="data" id="torrenttable" border="0">
                        <tr>
                            <th colspan="2" bgcolor="lightgrey">&nbsp;Query&nbsp;Type&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;%&nbsp;</th>
                        </tr>
@foreach ($column as $row)
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;{{ $row['name'] }}&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $row['value'] }}&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $row['perHour'] }}&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $row['pct'] }}&nbsp;%&nbsp;</td>
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
        <div class="nx-row">
@foreach ($statusColumns as $column)
                    <table data-nx="data" id="torrenttable" border="0">
                        <tr>
                            <th bgcolor="lightgrey">&nbsp;Variable&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;Value&nbsp;</th>
                        </tr>
@foreach ($column as $row)
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;{{ $row['name'] }}&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;{{ $row['value'] }}&nbsp;</td>
                        </tr>
@endforeach
                    </table>
@endforeach
        </div>
    </li>
@endif
</ul>
@endsection
