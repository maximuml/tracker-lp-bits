@props(['label' => null])
<span {{ $attributes->merge(['class' => 'nx-loading']) }} role="status">
    <span class="nx-loading__spinner" aria-hidden="true"></span>
    <span class="nx-loading__text">{{ $label ?? __('legacy/index.loading') }}</span>
</span>
