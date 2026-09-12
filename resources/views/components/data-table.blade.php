@props(['caption' => null, 'headers' => []])
<div class="nx-table-wrap">
    <table {{ $attributes->merge(['class' => 'nx-table']) }}>
        @if ($caption !== null && $caption !== '')
            <caption class="nx-table__caption">{{ $caption }}</caption>
        @endif
        @if (is_array($headers) && $headers !== [])
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
