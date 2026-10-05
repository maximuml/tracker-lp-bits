@props(['type' => 'button', 'variant' => 'default', 'href' => null])
@if ($href !== null)
    <a {{ $attributes->merge(['class' => 'inline-block cursor-pointer rounded-[3px] border px-3.5 py-1 text-xs no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nxm-accent '.(['primary' => 'border-nxm-accent bg-nxm-accent text-nxm-on-accent hover:bg-nxm-accent-hover', 'danger' => 'border-nxm-danger bg-nxm-danger text-nxm-on-danger hover:bg-nxm-danger'][$variant] ?? 'border-nxm-border bg-nxm-surface-alt text-nxm-text hover:bg-nxm-border')]) }} href="{{ $href }}">{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['class' => 'inline-block cursor-pointer rounded-[3px] border px-3.5 py-1 text-xs no-underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nxm-accent '.(['primary' => 'border-nxm-accent bg-nxm-accent text-nxm-on-accent hover:bg-nxm-accent-hover', 'danger' => 'border-nxm-danger bg-nxm-danger text-nxm-on-danger hover:bg-nxm-danger'][$variant] ?? 'border-nxm-border bg-nxm-surface-alt text-nxm-text hover:bg-nxm-border')]) }} type="{{ $type }}">{{ $slot }}</button>
@endif
