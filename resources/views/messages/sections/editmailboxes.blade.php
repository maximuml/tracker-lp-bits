<h1>{{ __('legacy/messages.text_editing_mailboxes') }}</h1>
<div>
<div class="nx-colhead">{{ __('legacy/messages.text_add_mailboxes') }}</div>
<div>{{ __('legacy/messages.text_extra_mailboxes_note') }}<br />
<form action="/messages" method="get">
<input type="hidden" name="action" value="editmailboxes2">
<input type="hidden" name="action2" value="add">
<input type="text" name="new1" size="40" maxlength="14"><br />
<input type="text" name="new2" size="40" maxlength="14"><br />
<input type="text" name="new3" size="40" maxlength="14"><br />
<input type="submit" value="{{ __('legacy/messages.submit_add') }}">
</form></div>
<div class="nx-colhead">{{ __('legacy/messages.text_edit_mailboxes') }}</div>
<div>{{ __('legacy/messages.text_edit_mailboxes_note') }}<br />{{ __('legacy/messages.text_edit_mailboxes_note_two') }}
<form action="/messages" method="get">
<input type="hidden" name="action" value="editmailboxes2">
<input type="hidden" name="action2" value="edit">
@if (! $editmailboxes['hasBoxes'])
<span align="center"><b>{{ __('legacy/messages.text_no_mailboxes_to_edit') }}</b></span>
@else
@foreach ($editmailboxes['boxes'] as $box)
<input type="text" name="edit{{ $box['id'] }}" value="{{ $box['name'] }}" size="40" maxlength="14"><br />
@endforeach
<input type="submit" value={{ __('legacy/messages.submit_edit') }}>
@endif
</form></div>
</div>
