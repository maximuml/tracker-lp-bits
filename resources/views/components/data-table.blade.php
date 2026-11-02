@props(['caption' => null, 'captionHidden' => false, 'headers' => []])
<div class="overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'w-full border-collapse nx-data-table']) }}>
        @if ($caption !== null && $caption !== '')
            <caption class="{{ $captionHidden ? 'sr-only' : 'pb-1 text-left font-bold' }}">{{ $caption }}</caption>
        @endif
        @isset($head)
            {{ $head }}
        @elseif (is_array($headers) && $headers !== [])
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endisset
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
