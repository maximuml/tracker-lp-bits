<div>
    @if ($previewMode)
        <div class="nx-box">{{ \App\Support\Format::formatComment($body) }}<br /><br /></div>
    @endif
    @if ($compact)
        <div class="bbcode-editor__head">
            <button type="button" class="nx-btn" aria-pressed="{{ $previewMode ? 'true' : 'false' }}" wire:click="{{ $previewMode ? 'unpreview' : 'preview' }}">{{ $previewMode ? __('functions.submit_edit') : __('functions.submit_preview') }}</button>
        </div>
    @endif
    <div @if ($previewMode) class="nx-hidden" @endif>
        <x-bbcode-editor :form="$form" :text="$text" :content="$body" :invalid="$invalid" :described-by="$describedBy" :label="$label" :wire-model="'body'" :compact="$compact" />
    </div>
    @if (! $compact)
        <div class="text-center mt-2">
            <input type="button" class="btn2" value="{{ $previewMode ? __('functions.submit_edit') : __('functions.submit_preview') }}" wire:click="{{ $previewMode ? 'unpreview' : 'preview' }}" />
        </div>
    @endif
</div>
