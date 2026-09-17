<form action="/messages" method="get">
<input type="hidden" name="action" value="viewmailbox">{{ __('legacy/messages.text_search') }}&nbsp;&nbsp;<input id="searchinput" name="keyword" type="text" value="{{ $viewmailbox['keyword'] }}" style="width: 200px"/>
{{ __('legacy/messages.text_in') }}&nbsp;<select name="place">
<option value="both" {{ $viewmailbox['place'] === 'both' ? ' selected' : '' }}>{{ __('legacy/messages.select_both') }}</option>
<option value="title" {{ $viewmailbox['place'] === 'title' ? ' selected' : '' }}>{{ __('legacy/messages.select_title') }}</option>
<option value="body" {{ $viewmailbox['place'] === 'body' ? ' selected' : '' }}>{{ __('legacy/messages.select_body') }}</option>
</select>
{{ __('legacy/messages.text_jump_to') }}<select name="box">
{{ $viewmailbox['jumpToBoxes'] ?? '' }}
</select> <input class=btn type="submit" value={{ __('legacy/messages.submit_go') }}></form>
