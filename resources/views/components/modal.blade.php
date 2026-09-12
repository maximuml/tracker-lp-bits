@props(['id', 'title', 'closeLabel' => 'Close'])
<div {{ $attributes->merge(['class' => 'nx-modal']) }} id="{{ $id }}" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" hidden>
    <div class="nx-modal__box">
        <div class="nx-modal__header">
            <h2 class="nx-modal__title" id="{{ $id }}-title">{{ $title }}</h2>
            <button type="button" class="nx-modal__close" data-modal-close="{{ $id }}" aria-label="{{ $closeLabel }}">&times;</button>
        </div>
        <div class="nx-modal__body">{{ $slot }}</div>
    </div>
</div>
