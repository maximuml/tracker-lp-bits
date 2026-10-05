{{-- Forum compose form (newtopic/reply/quotepost/editpost). Keeps the
     legacy JS hooks: form id/name, hidden postid/id/type inputs; preview
     lives in the livewire:bbcode-editor component. --}}
@props(['vm'])
<form id="compose" method="post" name="compose" action="/web/forums/post">
    @if ($vm->postid !== null)
    <input type="hidden" name="postid" value="{{ $vm->postid }}" />
    @endif
    <input type="hidden" name="id" value="{{ $vm->hiddenId }}" />
    <input type="hidden" name="type" value="{{ $vm->hiddenType }}" />
    @if (! $vm->titleHtml->isEmpty())
    <h1 class="text-center">{{ $vm->titleHtml }}</h1>
    @endif
    <x-frame :caption="$vm->frameCaption()" :center="true">
        <div class="nx-fgrid nx-fgrid--flat">
            @if ($vm->hasSubject)
            <div class="nx-fhead"><label for="subject">{{ __('legacy/functions.row_subject') }}</label></div>
            <div class="nx-fcell"><input type="text" id="subject" name="subject" maxlength="{{ $vm->maxSubjectLength }}" value="{{ $vm->subject }}"@if (isset($errors) && $errors->has('subject')) aria-invalid="true" aria-describedby="compose-subject-error"@endif />@if (isset($errors) && $errors->has('subject'))<p class="nx-field__error" id="compose-subject-error">{{ $errors->first('subject') }}</p>@endif</div>
            @endif
            <div class="nx-fhead"><label for="body">{{ __('legacy/functions.row_body') }}</label></div>
            <div class="nx-fcell"><livewire:bbcode-editor form="compose" text="body" :content="$vm->body" :invalid="isset($errors) && $errors->has('body')" :described-by="isset($errors) && $errors->has('body') ? 'compose-body-error' : ''" />@if (isset($errors) && $errors->has('body'))<p class="nx-field__error" id="compose-body-error">{{ $errors->first('body') }}</p>@endif</div>
            <div class="nx-ffull text-center"><input id="qr" type="submit" class="btn" value="{{ __('legacy/functions.submit_submit') }}" /></div>
        </div>
    </x-frame>
</form>
<p class="text-center"><a href="tags.php" target="_blank">{{ __('legacy/functions.text_tags') }}</a> | <a href="smilies.php" target="_blank">{{ __('legacy/functions.text_smilies') }}</a></p>
