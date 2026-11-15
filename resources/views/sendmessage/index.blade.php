@extends('layouts.app')

@section('title', $title ?? (__('sendmessage.head_send_message')))

@section('content')
<form id="compose" name="compose" class="nx-compose" method="post" action="/web/messages/send">
@csrf
<input type="hidden" name="receiver" value="{{ $vm->receiver }}">
@if ($vm->returnto !== '')<input type="hidden" name="returnto" value="{{ $vm->returnto }}">@endif
@if ($vm->replyto)
<input type="hidden" name="origmsg" value="{{ $vm->replyto }}">
@endif
<h1 class="nx-compose__title">{{ $vm->frameTitle ?? $title }}</h1>
<div class="nx-compose__field">
    <label for="subject">{{ __('functions.row_subject') }}</label>
    <input type="text" id="subject" name="subject" maxlength="100" value="{{ old('subject', $vm->subject) }}"@if (isset($errors) && $errors->has('subject')) aria-invalid="true" aria-describedby="compose-subject-error"@endif>
    @if (isset($errors) && $errors->has('subject'))<p class="nx-field__error" id="compose-subject-error">{{ $errors->first('subject') }}</p>@endif
</div>
<div class="nx-compose__field">
    <label for="body">{{ __('functions.row_body') }}</label>
    <livewire:bbcode-editor form="compose" text="body" :content="old('body', $vm->body)" :compact="true" :required="true" :invalid="isset($errors) && $errors->has('body')" :described-by="isset($errors) && $errors->has('body') ? 'compose-body-error' : ''" />
    @if (isset($errors) && $errors->has('body'))<p class="nx-field__error" id="compose-body-error">{{ $errors->first('body') }}</p>@endif
</div>
<div class="nx-compose__foot">
    <div class="nx-compose__opts">
        @if ($vm->replyto)<label><input type="checkbox" name="delete" value="yes" @checked($vm->deleteChecked)> {{ __('sendmessage.checkbox_delete_message_replying_to') }}</label>@endif
        <label><input type="checkbox" name="save" value="yes" @checked($vm->saveChecked)> {{ __('sendmessage.checkbox_save_message_to_sendbox') }}</label>
    </div>
    <input id="qr" type="submit" class="nx-btn nx-btn--primary" value="{{ __('messages.submit_send') }}" data-submit-once>
</div>
<p class="nx-compose__help"><a href="/web/tags" target="_blank" rel="noopener">{{ __('functions.text_tags') }}</a> · <a href="/web/smilies" target="_blank" rel="noopener">{{ __('functions.text_smilies') }}</a></p>
</form>
@endsection
