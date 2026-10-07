@props(['label' => null])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-nxm-text-dim']) }} role="status">
    <span class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-nxm-border border-t-nxm-accent motion-reduce:[animation-duration:2.5s]" aria-hidden="true"></span>
    <span>{{ $label ?? __('index.loading') }}</span>
</span>
