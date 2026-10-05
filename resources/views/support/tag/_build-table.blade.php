@props(['header', 'rows', 'tdClass'])
<table data-nx="data"><thead><tr>@foreach($header as $value)<th class="bg-nxm-surface-alt font-semibold" scope="col">{{ $value }}</th>@endforeach</tr></thead><tbody>@foreach($rows as $row)<tr>@foreach($header as $headerKey => $headerValue)<td class="{{ $tdClass }}">{{ $row[$headerKey] ?? '' }}</td>@endforeach</tr>@endforeach</tbody></table>
