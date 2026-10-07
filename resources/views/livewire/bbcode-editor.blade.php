<div>
    @if ($previewMode)
        <div class="nx-box">{{ \App\Support\Format::formatComment($body) }}<br /><br /></div>
    @endif
    <div @if ($previewMode) class="nx-hidden" @endif>
        <x-bbcode-editor :form="$form" :text="$text" :content="$body" :invalid="$invalid" :described-by="$describedBy" :label="$label" :wire-model="'body'" />
    </div>
    <div class="text-center mt-2">
        <input type="button" class="btn2" value="{{ $previewMode ? __('functions.submit_edit') : __('functions.submit_preview') }}" wire:click="{{ $previewMode ? 'unpreview' : 'preview' }}" />
    </div>
</div>
