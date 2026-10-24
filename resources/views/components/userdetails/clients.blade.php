<x-data-table caption="Clients" captionHidden>
<x-slot:head>
<thead>
<tr><th class="bg-nxm-surface-alt font-semibold" scope="col">Agent</th><th class="bg-nxm-surface-alt font-semibold" scope="col">IPV4</th><th class="bg-nxm-surface-alt font-semibold" scope="col">IPV6</th><th class="bg-nxm-surface-alt font-semibold" scope="col">Port</th></tr>
</thead>
</x-slot:head>
@foreach ($rows as $row)<tr><td>{{ $row['agent'] }}</td><td>{{ $row['ipv4'] }}</td><td>{{ $row['ipv6'] }}</td><td>{{ $row['port'] }}</td></tr>@endforeach</x-data-table>
