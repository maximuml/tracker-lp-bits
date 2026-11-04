<h2>{{ __('messages.text_editing_mailboxes') }}</h2>
<div>
<div class="nx-colhead">{{ __('messages.text_add_mailboxes') }}</div>
<div>{{ __('messages.text_extra_mailboxes_note') }}<br />
<form action="/web/messages/mailboxes" method="post">
@csrf
<input type="hidden" name="action2" value="add">
<input type="text" name="new1" size="40" maxlength="14" aria-label="{{ __('messages.text_add_mailboxes') }} 1"><br />
<input type="text" name="new2" size="40" maxlength="14" aria-label="{{ __('messages.text_add_mailboxes') }} 2"><br />
<input type="text" name="new3" size="40" maxlength="14" aria-label="{{ __('messages.text_add_mailboxes') }} 3"><br />
<input type="submit" class="nx-btn nx-btn--primary" value="{{ __('messages.submit_add') }}">
</form></div>
<div class="nx-colhead">{{ __('messages.text_edit_mailboxes') }}</div>
<div>{{ __('messages.text_edit_mailboxes_note') }}<br />{{ __('messages.text_edit_mailboxes_note_two') }}
<form action="/web/messages/mailboxes" method="post">
@csrf
<input type="hidden" name="action2" value="edit">
@if (! $editmailboxes['hasBoxes'])
<span><b>{{ __('messages.text_no_mailboxes_to_edit') }}</b></span>
@else
@foreach ($editmailboxes['boxes'] as $box)
<input type="text" name="edit{{ $box['id'] }}" value="{{ $box['name'] }}" size="40" maxlength="14" aria-label="{{ $box['name'] }}"><br />
@endforeach
<input type="submit" class="nx-btn nx-btn--primary" value="{{ __('messages.submit_edit') }}">
@endif
</form></div>
</div>
