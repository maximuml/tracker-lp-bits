<?php
echo '<h1 align=center>' . "\n"
   . '    Mysql Server Status'  . "\n"
   . '</h1>' . "\n";
?>

<table id="torrenttable" border="1"><tr><td>
<?php
echo 'This MySQL server has been running for '. \App\Repositories\MysqlStatsRepository::timespanFormat($uptimeSeconds) .'. It started up on '. \App\Repositories\MysqlStatsRepository::localisedDate($startTime) . "\n";
?>
</td></tr></table>

<ul>
    <li>
        <b>Server traffic:</b> These tables show the network traffic statistics of this MySQL server since its startup
        <br />
        <table border="0">
            <tr>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th colspan="2" bgcolor="lightgrey">&nbsp;Traffic&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&nbsp;Per Hour&nbsp;</th>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Received&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo implode(' ', \App\Repositories\MysqlStatsRepository::formatByteDown($bytesReceived)); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo implode(' ', \App\Repositories\MysqlStatsRepository::formatByteDown($bytesReceived * 3600 / max(1, $uptimeSeconds))); ?>&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Sent&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo implode(' ', \App\Repositories\MysqlStatsRepository::formatByteDown($bytesSent)); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo implode(' ', \App\Repositories\MysqlStatsRepository::formatByteDown($bytesSent * 3600 / max(1, $uptimeSeconds))); ?>&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="lightgrey">&nbsp;Total&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;<?php echo implode(' ', \App\Repositories\MysqlStatsRepository::formatByteDown($totalBytes)); ?>&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;<?php echo implode(' ', \App\Repositories\MysqlStatsRepository::formatByteDown($totalBytes * 3600 / max(1, $uptimeSeconds))); ?>&nbsp;</td>
                        </tr>
                    </table>
                </td>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th colspan="2" bgcolor="lightgrey">&nbsp;Connections&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per Hour&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;%&nbsp;</th>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Failed Attempts&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format($abortedConnects, 0, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format(($abortedConnects * 3600 / max(1, $uptimeSeconds)), 2, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo ($connections > 0) ? number_format(($abortedConnects * 100 / $connections), 2, '.', ',') . '&nbsp;%' : '---'; ?>&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;Aborted Clients&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format($abortedClients, 0, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format(($abortedClients * 3600 / max(1, $uptimeSeconds)), 2, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo ($connections > 0) ? number_format(($abortedClients * 100 / $connections), 2, '.', ',') . '&nbsp;%' : '---'; ?>&nbsp;</td>
                        </tr>
                        <tr>
                            <td bgcolor="lightgrey">&nbsp;Total&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;<?php echo number_format($connections, 0, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;<?php echo number_format(($connections * 3600 / max(1, $uptimeSeconds)), 2, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="lightgrey" align="right">&nbsp;<?php echo number_format(100, 2, '.', ',') ?>&nbsp;%&nbsp;</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </li>
    <br />
    <li>
        <?php echo '<b>Query Statistics:</b> Since it\'s start up, '. number_format($questions, 0, '.', ',').' queries have been sent to the server.'; ?>
        <table border="0">
            <tr>
                <td colspan="2">
                    <br />
                    <table id="torrenttable" border="0" align="right">
                        <tr>
                            <th bgcolor="lightgrey">&nbsp;Total&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Minute&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Second&nbsp;</th>
                        </tr>
                        <tr>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format($questions, 0, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format(($questions * 3600 / max(1, $uptimeSeconds)), 2, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format(($questions * 60 / max(1, $uptimeSeconds)), 2, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format(($questions / max(1, $uptimeSeconds)), 2, '.', ','); ?>&nbsp;</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th colspan="2" bgcolor="lightgrey">&nbsp;Query&nbsp;Type&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;%&nbsp;</th>
                        </tr>
<?php
$useBgcolorOne = true;
$countRows = 0;
$queryStatsDenominator = max(1, $questions - $connections);
foreach ($queryStats as $name => $value) {
?>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;<?php echo htmlspecialchars($name); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format((float) $value, 0, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format(((float) $value * 3600 / max(1, $uptimeSeconds)), 2, '.', ','); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo number_format(((float) $value * 100 / $queryStatsDenominator), 2, '.', ',') ?>&nbsp;%&nbsp;</td>
                        </tr>
<?php
    $useBgcolorOne = !$useBgcolorOne;
    if (++$countRows == ceil(count($queryStats) / 2)) {
        $useBgcolorOne = true;
?>
                    </table>
                </td>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th colspan="2" bgcolor="lightgrey">&nbsp;Query&nbsp;Type&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;&oslash;&nbsp;Per&nbsp;Hour&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;%&nbsp;</th>
                        </tr>
<?php
    }
}
?>
                    </table>
                </td>
            </tr>
        </table>
    </li>
<?php
if (!empty($serverStatus)) {
?>
    <br />
    <li>
        <b>More status variables</b><br />
        <table border="0">
            <tr>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th bgcolor="lightgrey">&nbsp;Variable&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;Value&nbsp;</th>
                        </tr>
<?php
    $useBgcolorOne = true;
    $countRows = 0;
    $totalRows = count($serverStatus);
    foreach ($serverStatus as $name => $value) {
?>
                        <tr>
                            <td bgcolor="#EFF3FF">&nbsp;<?php echo htmlspecialchars(str_replace('_', ' ', $name)); ?>&nbsp;</td>
                            <td bgcolor="#EFF3FF" align="right">&nbsp;<?php echo htmlspecialchars((string) $value); ?>&nbsp;</td>
                        </tr>
<?php
        $useBgcolorOne = !$useBgcolorOne;
        if (++$countRows == ceil($totalRows / 3) || $countRows == ceil($totalRows * 2 / 3)) {
            $useBgcolorOne = true;
?>
                    </table>
                </td>
                <td valign="top">
                    <table id="torrenttable" border="0">
                        <tr>
                            <th bgcolor="lightgrey">&nbsp;Variable&nbsp;</th>
                            <th bgcolor="lightgrey">&nbsp;Value&nbsp;</th>
                        </tr>
<?php
        }
    }
?>
                    </table>
                </td>
            </tr>
        </table>
    </li>
<?php
}
?>
</ul>
