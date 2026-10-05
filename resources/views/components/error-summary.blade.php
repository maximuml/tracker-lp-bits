<div {{ $attributes->merge(['class' => 'my-2 rounded border border-nxm-danger-border border-l-4 bg-nxm-danger-bg px-3 py-2 text-nxm-text']) }} role="alert" tabindex="-1">
    <p class="mb-1 font-bold">{{ $title !== '' ? $title : __('legacy/index.error_summary_title') }}</p>
    <ul class="m-0 pl-5 [&_a]:text-nxm-accent-text">
        @foreach ($items as $item)
            <li>
                @if ($item['href'] !== null)
                    <a href="{{ $item['href'] }}">{{ $item['message'] }}</a>
                @else
                    {{ $item['message'] }}
                @endif
            </li>
        @endforeach
    </ul>
</div>
