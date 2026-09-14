@extends('layouts.legacy')

@section('title', $lang_reports['text_reports'] ?? 'Reports')

@section('content')
<h1 align="center">{{ $lang_reports['text_reports'] ?? 'Reports' }}</h1>
<table border=1 cellspacing=0 cellpadding=5 align=center>
<form method=post action=takeupdate.php>
<tr>
    <td class="colhead"><nobr>{{ $lang_reports['col_added'] ?? 'Added' }}</nobr></td>
    <td class="colhead">{{ $lang_reports['col_reporter'] ?? 'Reporter' }}</td>
    <td class="colhead">{{ $lang_reports['col_reporting'] ?? 'Reporting' }}</td>
    <td class="colhead"><nobr>{{ $lang_reports['col_type'] ?? 'Type' }}</nobr></td>
    <td class="colhead">{{ $lang_reports['col_reason'] ?? 'Reason' }}</td>
    <td class="colhead"><nobr>{{ $lang_reports['col_dealt_with'] ?? 'Dealt with' }}</nobr></td>
    <td class="colhead"><nobr>{{ $lang_reports['col_action'] ?? 'Action' }}</nobr></td>
</tr>
@foreach ($rows as $row)
    <tr>
        <td class="rowfollow"><nobr>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['added_formatted']))</nobr></td>
        <td class="rowfollow">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['reporterHtml']))</td>
        <td class="rowfollow">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['reporting']))</td>
        <td class="rowfollow"><nobr>{{ $row['type_label'] }}</nobr></td>
        <td class="rowfollow">{{ $row['reason'] }}</td>
        <td class="rowfollow"><nobr>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['dealtwith_html']))</nobr></td>
        <td class="rowfollow"><input type="checkbox" name="delreport[]" value="{{ (int) $row['id'] }}" /></td>
    </tr>
@endforeach
<tr>
    <td class="colhead" colspan="7" align="right">
        <input type="submit" name="setdealt" value="{{ $lang_reports['submit_set_dealt'] ?? 'Set dealt' }}" />
        <input type="submit" name="delete" value="{{ $lang_reports['submit_delete'] ?? 'Delete' }}" />
    </td>
</tr>
</form>
</table>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
@endsection
