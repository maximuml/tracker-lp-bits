<h1>{{ $lang['text_editing_mailboxes'] ?? 'Editing mailboxes' }}</h1>
<table width={{ $contentWidth }} border="0" cellpadding="4" cellspacing="0">
<tr>
<td class="colhead" align="left">{{ $lang['text_add_mailboxes'] ?? 'Add mailboxes' }}</td>
</tr>
<tr>
<td align=left>{{ $lang['text_extra_mailboxes_note'] ?? '' }}<br />
<form action="/messages" method="get">
<input type="hidden" name="action" value="editmailboxes2">
<input type="hidden" name="action2" value="add">
<input type="text" name="new1" size="40" maxlength="14"><br />
<input type="text" name="new2" size="40" maxlength="14"><br />
<input type="text" name="new3" size="40" maxlength="14"><br />
<input type="submit" value="{{ $lang['submit_add'] ?? 'Add' }}">
</form></td>
</tr>
<tr>
<td class="colhead" align=left>{{ $lang['text_edit_mailboxes'] ?? 'Edit mailboxes' }}</td>
</tr>
<tr>
<td align=left>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_edit_mailboxes_note'] ?? ''))
<form action="/messages" method="get">
<input type="hidden" name="action" value="editmailboxes2">
<input type="hidden" name="action2" value="edit">
@if (! $editmailboxes['hasBoxes'])
<span align="center"><b>{{ $lang['text_no_mailboxes_to_edit'] ?? 'No mailboxes to edit' }}</b></span>
@else
@foreach ($editmailboxes['boxes'] as $box)
<input type="text" name="edit{{ $box['id'] }}" value="{{ $box['name'] }}" size="40" maxlength="14"><br />
@endforeach
<input type="submit" value={{ $lang['submit_edit'] ?? 'Edit' }}>
@endif
</form></td>
</tr>
</table>
