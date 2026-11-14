@if (! empty($rows))
<h2 class="text-center">..:: {{ $title }} ::..</h2>
<br /><br />
<x-data-table :caption="$title" captionHidden class="w-[80%] mx-auto"><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ 'Option Name' }}</th><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ ('Info')}}</th></tr></thead></x-slot:head>
@foreach ($rows as $row)
<tr>
    <td class="align-top px-2.5 py-1.5"><strong><a href="{{ $row['url'] }}">{{ $row['name'] }}</a></strong></td>
    <td class="align-top px-2.5 py-1.5">{{ $row['info'] }}</td>
</tr>
@endforeach
</x-data-table>
<br /><br />
@endif
