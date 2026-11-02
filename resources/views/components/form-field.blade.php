<div class="mb-3">
    <label class="mb-1 block font-bold" for="{{ $fieldId }}">
        {{ $label }}@if ($required)<span class="text-nxm-danger" aria-hidden="true">*</span>@endif
    </label>
    <div @class(['nx-pw' => $passwordToggle])>
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
        @if ($passwordToggle)
            <button type="button" class="nx-pw__toggle" data-pw-toggle
                    data-label-show="{{ __('functions.show_password') }}"
                    data-label-hide="{{ __('functions.hide_password') }}"
                    aria-label="{{ __('functions.show_password') }}" aria-pressed="false">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        @endif
    </div>
    @if ($hasError)
        <p class="mt-1 text-xs text-nxm-danger" id="{{ $fieldId }}-error">{{ $error }}</p>
    @endif
    @if ($hasHelp)
        <p class="mt-1 text-xs text-nxm-text-dim" id="{{ $fieldId }}-help">{{ $help }}</p>
    @endif
</div>
