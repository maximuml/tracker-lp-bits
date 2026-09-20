@props(['title' => '', 'type' => 'new', 'body' => '', 'hasSubject' => true, 'subject' => '', 'maxSubjectLength' => 100])
@if ((string) $title !== '')<h1 align="center">{{ $title }}</h1>@endif
<x-frame :caption="__('legacy/functions.' . ($type === 'reply' ? 'text_reply' : ($type === 'quote' ? 'text_quote' : ($type === 'edit' ? 'text_edit' : 'text_new'))))">
<table class="main" width="100%" border="1" cellspacing="0" cellpadding="5">
@if ($hasSubject)<tr><td class="rowhead">{{ __('legacy/functions.row_subject') }}</td><td class="rowfollow" align="left"><input type="text" name="subject" maxlength="{{ (int) $maxSubjectLength }}" value="{{ $subject }}" /></td></tr>
@endif
<tr><td class="rowhead" valign="top"><label for="body">{{ __('legacy/functions.row_body') }}</label></td><td class="rowfollow" align="left"><span class="nx-hidden" id="previewouter"></span><div id="editorouter"><x-bbcode-editor form="compose" text="body" :content="$body" />{{ $slot }}</div></td></tr>
<tr><td colspan="2" align="center"><table><tr><td class="embedded"><input id="qr" type="submit" class="btn" value="{{ __('legacy/functions.submit_submit') }}" /></td><td class="embedded"><input type="button" class="btn2" name="previewbutton" id="previewbutton" value="{{ __('legacy/functions.submit_preview') }}" data-preview-toggle="preview" /><input type="button" class="btn2 nx-hidden" name="unpreviewbutton" id="unpreviewbutton" value="{{ __('legacy/functions.submit_edit') }}" data-preview-toggle="unpreview" /></td></tr></table></td></tr>
</table>
</x-frame>
<p align="center"><a href="tags.php" target="_blank">{{ __('legacy/functions.text_tags') }}</a> | <a href="smilies.php" target="_blank">{{ __('legacy/functions.text_smilies') }}</a></p>
