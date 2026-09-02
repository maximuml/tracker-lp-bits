@php
/** @var array<string, mixed> $forward */
/** @var array<string, mixed> $lang */
/** @var array<string, mixed> $curUser */
$lang_messages = $lang;
$CONTENT_WIDTH = $contentWidth;
$CURUSER = $curUser;
$fw = $forward;
@endphp
<h1 align="center">{{ $lang_messages['text_forward_pm'] ?? 'Forward PM' }}</h1>
<table border="0" cellpadding="4" cellspacing="0"  width={{ $CONTENT_WIDTH }}>
<form action="/takemessage" method="post">
@csrf
<input type="hidden" name="forward" value="1">
<input type="hidden" name="origmsg" value="{{ $fw['pmId'] }}">
<tr>
<td class="rowhead" align="right">{{ $lang_messages['row_to'] ?? 'To' }}</td>
<td class="rowfollow" align=left><input type="text" name="to" style="width: 200px"></td>
</tr>
<tr>
<td class="rowhead" align="right">{{ $lang_messages['row_original_receiver'] ?? 'Original receiver' }}</td>
<td class="rowfollow" align=left>{!! $fw['fromName'] !!}</td>
</tr>
<tr>
<td class="rowhead" align="right">{{ $lang_messages['row_original_sender'] ?? 'Original sender' }}</td>
<td class="rowfollow" align=left>{!! $fw['origName'] !!}</td>
</tr>
<tr>
<td class="rowhead" align="right">{{ $lang_messages['row_subject'] ?? 'Subject' }}</td>
<td class="rowfollow" align=left><input type="text" name="subject" value="{{ $fw['subject'] }}" style="width: 500px"></td>
</tr>
<tr>
<td class="rowhead" align="right" valign="top"><nobr>{{ $lang_messages['row_message'] ?? 'Message' }}</nobr></td>
<td class="rowfollow" align=left><textarea name="body" style="width: 500px" rows="8"></textarea><br />{!! $fw['body'] !!}</td>
</tr>
<tr>
<td class=toolbox colspan="2" align="center"><input class=checkbox type="checkbox" name="save" value="yes"{{ ($CURUSER['savepms'] ?? '') === 'yes' ? ' checked' : '' }}>{{ $lang_messages['checkbox_save_message'] ?? 'Save message' }}&nbsp;
<input type="submit" class="btn" value={{ $lang_messages['submit_forward'] ?? 'Forward' }}></td>
</tr>
</table>
</form>
