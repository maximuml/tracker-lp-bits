{{-- Forum compose form (newtopic/reply/quotepost/editpost). Keeps the
     legacy JS hooks: form id/name, hidden postid/id/type inputs; preview
     lives in the livewire:bbcode-editor component. --}}
@props(['vm'])
<form id="compose" name="compose" class="nx-compose" method="post" action="/web/forums/post">@csrf
    @if ($vm->postid !== null)
    <input type="hidden" name="postid" value="{{ $vm->postid }}" />
    @endif
    <input type="hidden" name="id" value="{{ $vm->hiddenId }}" />
    <input type="hidden" name="type" value="{{ $vm->hiddenType }}" />
    <h1 class="nx-compose__title">@if (! $vm->titleHtml->isEmpty()){{ $vm->titleHtml }}@else{{ $vm->frameCaption() }}@endif</h1>
    @if ($vm->hasSubject)
    <div class="nx-compose__field">
        <label for="subject">{{ __('functions.row_subject') }}</label>
        <input type="text" id="subject" name="subject" maxlength="{{ $vm->maxSubjectLength }}" value="{{ $vm->subject }}"@if (isset($errors) && $errors->has('subject')) aria-invalid="true" aria-describedby="compose-subject-error"@endif>
        @if (isset($errors) && $errors->has('subject'))<p class="nx-field__error" id="compose-subject-error">{{ $errors->first('subject') }}</p>@endif
    </div>
    @endif
    <div class="nx-compose__field">
        <label for="body">{{ __('functions.row_body') }}</label>
        <livewire:bbcode-editor form="compose" text="body" :content="$vm->body" :compact="true" :invalid="isset($errors) && $errors->has('body')" :described-by="isset($errors) && $errors->has('body') ? 'compose-body-error' : ''" />
        @if (isset($errors) && $errors->has('body'))<p class="nx-field__error" id="compose-body-error">{{ $errors->first('body') }}</p>@endif
    </div>
    <div class="nx-compose__foot">
        <input id="qr" type="submit" class="nx-btn nx-btn--primary" value="{{ __('functions.submit_submit') }}" data-submit-once>
    </div>
    <p class="nx-compose__help"><a href="/web/tags" target="_blank">{{ __('functions.text_tags') }}</a> · <a href="/web/smilies" target="_blank">{{ __('functions.text_smilies') }}</a></p>
</form>
