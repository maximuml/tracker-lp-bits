@php
/** @var array<string, mixed> $editmailboxes */
/** @var array<string, mixed> $lang */
$lang_messages = $lang;
$CONTENT_WIDTH = $contentWidth;
$em = $editmailboxes;
@endphp
<h1>{{ $lang_messages['text_editing_mailboxes'] ?? 'Editing mailboxes' }}</h1>
<table width={{ $CONTENT_WIDTH }} border="0" cellpadding="4" cellspacing="0">
<tr>
<td class="colhead" align="left">{{ $lang_messages['text_add_mailboxes'] ?? 'Add mailboxes' }}</td>
</tr>
<tr>
<td align=left>{{ $lang_messages['text_extra_mailboxes_note'] ?? '' }}<br />
<form action="messages.php" method="get">
<input type="hidden" name="action" value="editmailboxes2">
<input type="hidden" name="action2" value="add">
<input type="text" name="new1" size="40" maxlength="14"><br />
<input type="text" name="new2" size="40" maxlength="14"><br />
<input type="text" name="new3" size="40" maxlength="14"><br />
<input type="submit" value="{{ $lang_messages['submit_add'] ?? 'Add' }}">
</form></td>
</tr>
<tr>
<td class="colhead" align=left>{{ $lang_messages['text_edit_mailboxes'] ?? 'Edit mailboxes' }}</td>
</tr>
<tr>
<td align=left>{{ $lang_messages['text_edit_mailboxes_note'] ?? '' }}
<form action="messages.php" method="get">
<input type="hidden" name="action" value="editmailboxes2">
<input type="hidden" name="action2" value="edit">
@if (! $em['hasBoxes'])
<span align="center"><b>{{ $lang_messages['text_no_mailboxes_to_edit'] ?? 'No mailboxes to edit' }}</b></span>
@else
@foreach ($em['boxes'] as $box)
<input type="text" name="edit{{ $box['id'] }}" value="{{ $box['name'] }}" size="40" maxlength="14"><br />
@endforeach
<input type="submit" value={{ $lang_messages['submit_edit'] ?? 'Edit' }}>
@endif
</form></td>
</tr>
</table>
