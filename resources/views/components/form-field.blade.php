<div class="mb-3">
    <label class="mb-1 block font-bold" for="{{ $fieldId }}">
        {{ $label }}@if ($required)<span class="text-nxm-danger" aria-hidden="true">*</span>@endif
    </label>
    <input
        {{ $attributes->merge(['class' => 'box-border w-full rounded-[3px] border border-nxm-border px-1.5 py-1 aria-invalid:border-nxm-danger focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-nxm-accent']) }}
        type="{{ $type }}"
        id="{{ $fieldId }}"
        name="{{ $name }}"
        @if ($value !== null) value="{{ $value }}" @endif
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($describedBy !== []) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
    >
    @if ($hasError)
        <p class="mt-1 text-xs text-nxm-danger" id="{{ $fieldId }}-error">{{ $error }}</p>
    @endif
    @if ($hasHelp)
        <p class="mt-1 text-xs text-nxm-text-dim" id="{{ $fieldId }}-help">{{ $help }}</p>
    @endif
</div>
