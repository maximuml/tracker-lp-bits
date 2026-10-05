@props(['title' => '', 'type' => 'new', 'body' => '', 'hasSubject' => true, 'subject' => '', 'maxSubjectLength' => 100])
@if ((string) $title !== '')<h1 class="text-center">{{ $title }}</h1>@endif
<x-frame :caption="__('legacy/functions.' . ($type === 'reply' ? 'text_reply' : ($type === 'quote' ? 'text_quote' : ($type === 'edit' ? 'text_edit' : 'text_new'))))">
<div class="nx-fgrid nx-fgrid--flat">
@if ($hasSubject)<div class="nx-fhead"><label for="subject">{{ __('legacy/functions.row_subject') }}</label></div><div class="nx-fcell"><input type="text" id="subject" name="subject" maxlength="{{ (int) $maxSubjectLength }}" value="{{ $subject }}"@if (isset($errors) && $errors->has('subject')) aria-invalid="true" aria-describedby="compose-subject-error"@endif />@if (isset($errors) && $errors->has('subject'))<p class="nx-field__error" id="compose-subject-error">{{ $errors->first('subject') }}</p>@endif</div>
@endif
<div class="nx-fhead"><label for="body">{{ __('legacy/functions.row_body') }}</label></div><div class="nx-fcell"><span class="nx-hidden" id="previewouter"></span><div id="editorouter"><x-bbcode-editor form="compose" text="body" :content="$body" :invalid="isset($errors) && $errors->has('body')" :described-by="isset($errors) && $errors->has('body') ? 'compose-body-error' : ''" />@if (isset($errors) && $errors->has('body'))<p class="nx-field__error" id="compose-body-error">{{ $errors->first('body') }}</p>@endif{{ $slot }}</div></div>
<div class="nx-ffull text-center"><input id="qr" type="submit" class="btn" value="{{ __('legacy/functions.submit_submit') }}" />
    <input type="button" class="btn2" name="previewbutton" id="previewbutton" value="{{ __('legacy/functions.submit_preview') }}" data-preview-toggle="preview" /><input type="button" class="btn2 nx-hidden" name="unpreviewbutton" id="unpreviewbutton" value="{{ __('legacy/functions.submit_edit') }}" data-preview-toggle="unpreview" /></div>
</div>
</x-frame>
<p class="text-center"><a href="tags.php" target="_blank">{{ __('legacy/functions.text_tags') }}</a> | <a href="smilies.php" target="_blank">{{ __('legacy/functions.text_smilies') }}</a></p>
