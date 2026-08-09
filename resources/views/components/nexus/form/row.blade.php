@props([
    'label' => null,
    'for' => null,
    'error' => null,
    'required' => false,
    'help' => null,
])

<div {{ $attributes->merge(['class' => 'grid grid-cols-1 gap-2 md:grid-cols-[12rem_1fr] md:gap-4']) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="font-medium text-nexus-text md:pt-2">
            {{ $label }}
            @if ($required)
                <span class="text-nexus-danger">*</span>
            @endif
        </label>
    @endif

    <div>
        {{ $slot }}

        @if ($error)
            <p class="mt-1 text-sm text-nexus-danger">{{ $error }}</p>
        @endif

        @if ($help)
            <p class="mt-1 text-sm text-nexus-muted">{{ $help }}</p>
        @endif
    </div>
</div>
