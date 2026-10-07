@props(['title' => '', 'type' => 'new', 'body' => '', 'hasSubject' => true, 'subject' => '', 'maxSubjectLength' => 100])
@if ((string) $title !== '')<h1 class="text-center">{{ $title }}</h1>@endif
<x-frame :caption="__('functions.' . ($type === 'reply' ? 'text_reply' : ($type === 'quote' ? 'text_quote' : ($type === 'edit' ? 'text_edit' : 'text_new'))))">
<div class="nx-fgrid nx-fgrid--flat">
@if ($hasSubject)<div class="nx-fhead"><label for="subject">{{ __('functions.row_subject') }}</label></div><div class="nx-fcell"><input type="text" id="subject" name="subject" maxlength="{{ (int) $maxSubjectLength }}" value="{{ $subject }}"@if (isset($errors) && $errors->has('subject')) aria-invalid="true" aria-describedby="compose-subject-error"@endif />@if (isset($errors) && $errors->has('subject'))<p class="nx-field__error" id="compose-subject-error">{{ $errors->first('subject') }}</p>@endif</div>
@endif
<div class="nx-fhead"><label for="body">{{ __('functions.row_body') }}</label></div><div class="nx-fcell"><livewire:bbcode-editor form="compose" text="body" :content="$body" :invalid="isset($errors) && $errors->has('body')" :described-by="isset($errors) && $errors->has('body') ? 'compose-body-error' : ''" />@if (isset($errors) && $errors->has('body'))<p class="nx-field__error" id="compose-body-error">{{ $errors->first('body') }}</p>@endif{{ $slot }}</div>
<div class="nx-ffull text-center"><input id="qr" type="submit" class="btn" value="{{ __('functions.submit_submit') }}" /></div>
</div>
</x-frame>
<p class="text-center"><a href="/web/tags" target="_blank">{{ __('functions.text_tags') }}</a> | <a href="/web/smilies" target="_blank">{{ __('functions.text_smilies') }}</a></p>
