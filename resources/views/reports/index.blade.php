@extends('layouts.app')

@section('title', __('reports.text_reports'))

@section('content')
<x-page :title="__('reports.text_reports')" width="list">
<x-data-table :caption="__('reports.text_reports')" captionHidden class="mx-auto">
<form method=post action="/web/system/update">@csrf
<tr>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><span class="nx-nobr">{{ __('reports.col_added')}}</span></th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('reports.col_reporter')}}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('reports.col_reporting')}}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><span class="nx-nobr">{{ __('reports.col_type')}}</span></th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('reports.col_reason')}}</th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><span class="nx-nobr">{{ __('reports.col_dealt_with')}}</span></th>
    <th class="bg-nxm-surface-alt font-semibold" scope="col"><span class="nx-nobr">{{ __('reports.col_action')}}</span></th>
</tr>
@foreach ($vm->rows as $row)
    <tr>
        <td class="align-top px-2.5 py-1.5"><span class="nx-nobr">{{ $row['added_formatted'] }}</span></td>
        <td class="align-top px-2.5 py-1.5">{{ $row['reporterHtml'] }}</td>
        <td class="align-top px-2.5 py-1.5">{{ $row['reporting'] }}</td>
        <td class="align-top px-2.5 py-1.5"><span class="nx-nobr">{{ $row['type_label'] }}</span></td>
        <td class="align-top px-2.5 py-1.5">{{ $row['reason'] }}</td>
        <td class="align-top px-2.5 py-1.5"><span class="nx-nobr">@if ($row['dealtwith'])<span class="text-nxm-success">{{ __('reports.text_yes') }}</span> - {{ $row['dealtbyHtml'] }}@else<span class="text-nxm-danger">{{ __('reports.text_no') }}</span>@endif</span></td>
        <td class="align-top px-2.5 py-1.5"><input type="checkbox" name="delreport[]" value="{{ (int) $row['id'] }}" aria-label="{{ __('reports.aria_delete_report', ['id' => (int) $row['id']]) }}" /></td>
    </tr>
@endforeach
<tr>
    <th class="bg-nxm-surface-alt font-semibold text-right" colspan="7" scope="colgroup">
        <input type="submit" name="setdealt" class="nx-btn nx-btn--primary" value="{{ __('reports.submit_set_dealt')}}"  data-submit-once />
        <input type="submit" name="delete" class="nx-btn nx-btn--danger" value="{{ __('reports.submit_delete')}}" data-confirm="{{ __('reports.confirm_delete_reports') }}"  data-submit-once />
    </th>
</tr>
</form>
</x-data-table>
<x-pagination :page="$vm->pager['page']" :pages="$vm->pager['pages']" :href="$vm->pager['href']" :prev-label="$vm->pager['prevLabel']" :next-label="$vm->pager['nextLabel']" :zero-based="$vm->pager['zeroBased']" label="Pagination bottom" />
</x-page>
@endsection
