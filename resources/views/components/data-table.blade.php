@props(['caption' => null, 'captionHidden' => false, 'headers' => []])
<div class="overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'w-full border-collapse [&_td]:border [&_td]:border-nxm-border [&_td]:px-2 [&_td]:py-1.5 [&_td]:text-left [&_th]:border [&_th]:border-nxm-border [&_th]:px-2 [&_th]:py-1.5 [&_th]:text-left [&_thead_th]:bg-nxm-surface-alt [&_thead_th]:text-nxm-text']) }}>
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
