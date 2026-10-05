@props(['id', 'title', 'closeLabel' => 'Close'])
<div {{ $attributes->merge(['class' => 'fixed inset-0 z-[9998] bg-nxm-overlay']) }} id="{{ $id }}" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" hidden>
    <div class="relative mx-auto mt-[10vh] max-w-[520px] rounded bg-nxm-surface p-4 text-nxm-text">
        <div class="mb-2 flex items-center justify-between">
            <h2 class="m-0 text-base" id="{{ $id }}-title">{{ $title }}</h2>
            <button type="button" class="cursor-pointer border-0 bg-transparent px-2 py-1 text-xl leading-none focus-visible:outline-2 focus-visible:outline-nxm-accent" data-modal-close="{{ $id }}" aria-label="{{ $closeLabel }}">&times;</button>
        </div>
        <div>{{ $slot }}</div>
    </div>
</div>
