@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('legacy/reports.text_reports'))

@section('content')
<h1 class="text-center">{{ __('legacy/reports.text_reports')}}</h1>
<x-data-table :caption="__('legacy/reports.text_reports')" captionHidden class="mx-auto">
<form method=post action=/takeupdate>
<tr>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('legacy/reports.col_added')}}</nobr></th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/reports.col_reporter')}}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/reports.col_reporting')}}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('legacy/reports.col_type')}}</nobr></th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/reports.col_reason')}}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('legacy/reports.col_dealt_with')}}</nobr></th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><nobr>{{ __('legacy/reports.col_action')}}</nobr></th>
</tr>
@foreach ($rows as $row)
    <tr>
        <td class="align-top px-2.5 py-1.5"><nobr>{{ $row['added_formatted'] }}</nobr></td>
        <td class="align-top px-2.5 py-1.5">{{ $row['reporterHtml'] }}</td>
        <td class="align-top px-2.5 py-1.5">{{ $row['reporting'] }}</td>
        <td class="align-top px-2.5 py-1.5"><nobr>{{ $row['type_label'] }}</nobr></td>
        <td class="align-top px-2.5 py-1.5">{{ $row['reason'] }}</td>
        <td class="align-top px-2.5 py-1.5"><nobr>@if ($row['dealtwith'])<span class="text-nxm-success">{{ __('legacy/reports.text_yes') }}</span> - {{ $row['dealtbyHtml'] }}@else<span class="text-nxm-danger">{{ __('legacy/reports.text_no') }}</span>@endif</nobr></td>
        <td class="align-top px-2.5 py-1.5"><input type="checkbox" name="delreport[]" value="{{ (int) $row['id'] }}" /></td>
    </tr>
@endforeach
<tr>
    <th class="bg-nxm-surface-alt font-semibold text-right" colspan="7" scope="colgroup">
        <input type="submit" name="setdealt" value="{{ __('legacy/reports.submit_set_dealt')}}" />
        <input type="submit" name="delete" value="{{ __('legacy/reports.submit_delete')}}" />
    </th>
</tr>
</form>
</x-data-table>
{{ $pagerbottom ?? '' }}
@endsection
