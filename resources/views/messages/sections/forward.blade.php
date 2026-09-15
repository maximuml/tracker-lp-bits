<h1 align="center">{{ $lang['text_forward_pm'] ?? 'Forward PM' }}</h1>
<form action="/takemessage" method="post">
@csrf
<input type="hidden" name="forward" value="1">
<input type="hidden" name="origmsg" value="{{ $forward['pmId'] }}">
<div class="nx-fgrid nx-fgrid--flat">
<div class="nx-fhead">{{ $lang['row_to'] ?? 'To' }}</div>
<div class="nx-fcell"><input type="text" name="to" style="width: 200px"></div>
<div class="nx-fhead">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang['row_original_receiver'] ?? 'Original receiver'))</div>
<div class="nx-fcell">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($forward['fromName'] ?? ''))</div>
<div class="nx-fhead">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang['row_original_sender'] ?? 'Original sender'))</div>
<div class="nx-fcell">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($forward['origName'] ?? ''))</div>
<div class="nx-fhead">{{ $lang['row_subject'] ?? 'Subject' }}</div>
<div class="nx-fcell"><input type="text" name="subject" value="{{ $forward['subject'] }}" style="width: 500px"></div>
<div class="nx-fhead"><nobr>{{ $lang['row_message'] ?? 'Message' }}</nobr></div>
<div class="nx-fcell"><textarea name="body" style="width: 500px" rows="8"></textarea><br />@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($forward['body'] ?? ''))</div>
<div class="nx-ffull nx-center"><input class=checkbox type="checkbox" name="save" value="yes"{{ \App\Support\LegacyYesNo::isYes($curUser['savepms'] ?? null) ? ' checked' : '' }}>{{ $lang['checkbox_save_message'] ?? 'Save message' }}&nbsp;
<input type="submit" class="btn" value={{ $lang['submit_forward'] ?? 'Forward' }}></div>
</div>
</form>
