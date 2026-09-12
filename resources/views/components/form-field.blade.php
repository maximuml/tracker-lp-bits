<div class="nx-field">
    <label class="nx-field__label" for="{{ $fieldId }}">
        {{ $label }}@if ($required)<span class="nx-field__required" aria-hidden="true">*</span>@endif
    </label>
    <input
        {{ $attributes->merge(['class' => 'nx-field__input']) }}
        type="{{ $type }}"
        id="{{ $fieldId }}"
        name="{{ $name }}"
        @if ($value !== null) value="{{ $value }}" @endif
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" @endif
        @if ($describedBy !== []) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
    >
    @if ($hasError)
        <p class="nx-field__error" id="{{ $fieldId }}-error">{{ $error }}</p>
    @endif
    @if ($hasHelp)
        <p class="nx-field__help" id="{{ $fieldId }}-help">{{ $help }}</p>
    @endif
</div>
