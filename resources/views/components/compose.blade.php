@props(['title' => '', 'type' => 'new', 'body' => '', 'hasSubject' => true, 'subject' => '', 'maxSubjectLength' => 100])
@if ((string) $title !== '')<h1 class="nx-compose__title">{{ $title }}</h1>@endif
@if ($hasSubject)
<div class="nx-compose__field">
    <label for="subject">{{ __('functions.row_subject') }}</label>
    <input type="text" id="subject" name="subject" maxlength="{{ (int) $maxSubjectLength }}" value="{{ $subject }}"@if (isset($errors) && $errors->has('subject')) aria-invalid="true" aria-describedby="compose-subject-error"@endif>
    @if (isset($errors) && $errors->has('subject'))<p class="nx-field__error" id="compose-subject-error">{{ $errors->first('subject') }}</p>@endif
</div>
@endif
<div class="nx-compose__field">
    <label for="body">{{ __('functions.row_body') }}</label>
    <livewire:bbcode-editor form="compose" text="body" :content="$body" :compact="true" :required="true" :invalid="isset($errors) && $errors->has('body')" :described-by="isset($errors) && $errors->has('body') ? 'compose-body-error' : ''" />
    @if (isset($errors) && $errors->has('body'))<p class="nx-field__error" id="compose-body-error">{{ $errors->first('body') }}</p>@endif
</div>
<div class="nx-compose__foot">
    @if (trim((string) $slot) !== '')<div class="nx-compose__opts">{{ $slot }}</div>@endif
    <input id="qr" type="submit" class="nx-btn nx-btn--primary" value="{{ __('functions.submit_submit') }}" data-submit-once>
</div>
<p class="nx-compose__help"><a href="/web/tags" target="_blank" rel="noopener">{{ __('functions.text_tags') }}</a> · <a href="/web/smilies" target="_blank" rel="noopener">{{ __('functions.text_smilies') }}</a></p>
