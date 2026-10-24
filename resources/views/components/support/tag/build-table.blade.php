@props(['header', 'rows', 'tdClass'])
<x-data-table :headers="array_values($header)">@foreach($rows as $row)<tr>@foreach($header as $headerKey => $headerValue)<td class="{{ $tdClass }}">{{ $row[$headerKey] ?? '' }}</td>@endforeach</tr>@endforeach</x-data-table>
