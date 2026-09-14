<form action="/messages" method="get">
<input type="hidden" name="action" value="viewmailbox">{{ $lang['text_search'] ?? 'Search' }}&nbsp;&nbsp;<input id="searchinput" name="keyword" type="text" value="{{ $viewmailbox['keyword'] }}" style="width: 200px"/>
{{ $lang['text_in'] ?? 'in' }}&nbsp;<select name="place">
<option value="both" {{ $viewmailbox['place'] === 'both' ? ' selected' : '' }}>{{ $lang['select_both'] ?? 'Both' }}</option>
<option value="title" {{ $viewmailbox['place'] === 'title' ? ' selected' : '' }}>{{ $lang['select_title'] ?? 'Title' }}</option>
<option value="body" {{ $viewmailbox['place'] === 'body' ? ' selected' : '' }}>{{ $lang['select_body'] ?? 'Body' }}</option>
</select>
{{ $lang['text_jump_to'] ?? 'Jump to' }}<select name="box">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($viewmailbox['jumpToBoxes'] ?? ''))
</select> <input class=btn type="submit" value={{ $lang['submit_go'] ?? 'Go' }}></form>
