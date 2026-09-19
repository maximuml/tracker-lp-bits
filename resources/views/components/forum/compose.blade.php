{{-- Forum compose form (newtopic/reply/quotepost/editpost). Keeps the
     legacy JS hooks: form id/name, hidden postid/id/type inputs,
     #previewouter/#editorouter and the data-preview-toggle buttons
     handled by bbcode-editor.js/common.js. --}}
@props(['vm'])
<form id="compose" method="post" name="compose" action="?action=post">
    @if ($vm->postid !== null)
    <input type="hidden" name="postid" value="{{ $vm->postid }}" />
    @endif
    <input type="hidden" name="id" value="{{ $vm->hiddenId }}" />
    <input type="hidden" name="type" value="{{ $vm->hiddenType }}" />
    @if (! $vm->titleHtml->isEmpty())
    <h1 align="center">{{ $vm->titleHtml }}</h1>
    @endif
    <x-frame :caption="$vm->frameCaption()" :center="true">
        <div class="nx-fgrid nx-fgrid--flat">
            @if ($vm->hasSubject)
            <div class="nx-fhead">{{ __('legacy/functions.row_subject') }}</div>
            <div class="nx-fcell"><input type="text" name="subject" maxlength="{{ $vm->maxSubjectLength }}" value="{{ $vm->subject }}" /></div>
            @endif
            <div class="nx-fhead">{{ __('legacy/functions.row_body') }}</div>
            <div class="nx-fcell"><span class="nx-hidden" id="previewouter"></span><div id="editorouter"><x-bbcode-editor form="compose" text="body" :content="$vm->body" /></div></div>
            <div class="nx-ffull nx-center"><input id="qr" type="submit" class="btn" value="{{ __('legacy/functions.submit_submit') }}" />
                <input type="button" class="btn2" name="previewbutton" id="previewbutton" value="{{ __('legacy/functions.submit_preview') }}" data-preview-toggle="preview" />
                <input type="button" class="btn2 nx-hidden" name="unpreviewbutton" id="unpreviewbutton" value="{{ __('legacy/functions.submit_edit') }}" data-preview-toggle="unpreview" /></div>
        </div>
    </x-frame>
</form>
<p align="center"><a href="tags.php" target="_blank">{{ __('legacy/functions.text_tags') }}</a> | <a href="smilies.php" target="_blank">{{ __('legacy/functions.text_smilies') }}</a></p>
