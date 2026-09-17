@extends('layouts.legacy')

@section('title', __('legacy/reports.text_reports'))

@section('content')
<h1 align="center">{{ __('legacy/reports.text_reports')}}</h1>
<table data-nx="data" border=1 cellspacing=0 cellpadding=5 align=center>
<form method=post action=takeupdate.php>
<tr>
    <td class="colhead"><nobr>{{ __('legacy/reports.col_added')}}</nobr></td>
    <td class="colhead">{{ __('legacy/reports.col_reporter')}}</td>
    <td class="colhead">{{ __('legacy/reports.col_reporting')}}</td>
    <td class="colhead"><nobr>{{ __('legacy/reports.col_type')}}</nobr></td>
    <td class="colhead">{{ __('legacy/reports.col_reason')}}</td>
    <td class="colhead"><nobr>{{ __('legacy/reports.col_dealt_with')}}</nobr></td>
    <td class="colhead"><nobr>{{ __('legacy/reports.col_action')}}</nobr></td>
</tr>
@foreach ($rows as $row)
    <tr>
        <td class="rowfollow"><nobr>{{ $row['added_formatted'] }}</nobr></td>
        <td class="rowfollow">{{ $row['reporterHtml'] }}</td>
        <td class="rowfollow">{{ $row['reporting'] }}</td>
        <td class="rowfollow"><nobr>{{ $row['type_label'] }}</nobr></td>
        <td class="rowfollow">{{ $row['reason'] }}</td>
        <td class="rowfollow"><nobr>{{ $row['dealtwith_html'] }}</nobr></td>
        <td class="rowfollow"><input type="checkbox" name="delreport[]" value="{{ (int) $row['id'] }}" /></td>
    </tr>
@endforeach
<tr>
    <td class="colhead" colspan="7" align="right">
        <input type="submit" name="setdealt" value="{{ __('legacy/reports.submit_set_dealt')}}" />
        <input type="submit" name="delete" value="{{ __('legacy/reports.submit_delete')}}" />
    </td>
</tr>
</form>
</table>
{{ $pagerbottom ?? '' }}
@endsection
