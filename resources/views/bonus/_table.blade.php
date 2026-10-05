<x-data-table :caption="__('legacy/functions.text_bonus')" captionHidden>
    <x-slot:head>
        <thead>
            <tr>@foreach ($headers as $header)<th scope="col">{{ $header }}</th>@endforeach</tr>
        </thead>
    </x-slot:head>
    <tr>@foreach ($baseRow as $cell)<td>{{ $cell }}</td>@endforeach<td rowspan="{{ $rowSpan }}">{{ $total }}</td></tr>
    @foreach ($extraRows as $extraRow)<tr>@foreach ($extraRow as $cell)<td>{{ $cell }}</td>@endforeach</tr>@endforeach
</x-data-table>
