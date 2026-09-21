@extends('layouts.legacy')

@section('title', __('legacy/reports.text_reports'))

@section('content')
<h1 class="nx-center">{{ __('legacy/reports.text_reports')}}</h1>
<table data-nx="data" class="nx-mx-auto">
<form method=post action=takeupdate.php>
<tr>
    <th class="colhead" scope="col"><nobr>{{ __('legacy/reports.col_added')}}</nobr></th>
    <th class="colhead" scope="col">{{ __('legacy/reports.col_reporter')}}</th>
    <th class="colhead" scope="col">{{ __('legacy/reports.col_reporting')}}</th>
    <th class="colhead" scope="col"><nobr>{{ __('legacy/reports.col_type')}}</nobr></th>
    <th class="colhead" scope="col">{{ __('legacy/reports.col_reason')}}</th>
    <th class="colhead" scope="col"><nobr>{{ __('legacy/reports.col_dealt_with')}}</nobr></th>
    <th class="colhead" scope="col"><nobr>{{ __('legacy/reports.col_action')}}</nobr></th>
</tr>
@foreach ($rows as $row)
    <tr>
        <td class="rowfollow"><nobr>{{ $row['added_formatted'] }}</nobr></td>
        <td class="rowfollow">{{ $row['reporterHtml'] }}</td>
        <td class="rowfollow">{{ $row['reporting'] }}</td>
        <td class="rowfollow"><nobr>{{ $row['type_label'] }}</nobr></td>
        <td class="rowfollow">{{ $row['reason'] }}</td>
        <td class="rowfollow"><nobr>@if ($row['dealtwith'])<span class="nx-color-green">{{ __('legacy/reports.text_yes') }}</span> - {{ $row['dealtbyHtml'] }}@else<span class="nx-color-red">{{ __('legacy/reports.text_no') }}</span>@endif</nobr></td>
        <td class="rowfollow"><input type="checkbox" name="delreport[]" value="{{ (int) $row['id'] }}" /></td>
    </tr>
@endforeach
<tr>
    <th class="colhead nx-align-right" colspan="7" scope="colgroup">
        <input type="submit" name="setdealt" value="{{ __('legacy/reports.submit_set_dealt')}}" />
        <input type="submit" name="delete" value="{{ __('legacy/reports.submit_delete')}}" />
    </th>
</tr>
</form>
</table>
{{ $pagerbottom ?? '' }}
@endsection
