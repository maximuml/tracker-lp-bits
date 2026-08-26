@php
/** @var array<string, mixed> $viewmailbox */
$lang_messages = (array) (\app(\App\Support\Globals::class)->get('lang_messages') ?? []);
$vm = $viewmailbox;
@endphp
<form action="messages.php" method="get">
<input type="hidden" name="action" value="viewmailbox">{{ $lang_messages['text_search'] ?? 'Search' }}&nbsp;&nbsp;<input id="searchinput" name="keyword" type="text" value="{{ $vm['keyword'] }}" style="width: 200px"/>
{{ $lang_messages['text_in'] ?? 'in' }}&nbsp;<select name="place">
<option value="both" {{ $vm['place'] === 'both' ? ' selected' : '' }}>{{ $lang_messages['select_both'] ?? 'Both' }}</option>
<option value="title" {{ $vm['place'] === 'title' ? ' selected' : '' }}>{{ $lang_messages['select_title'] ?? 'Title' }}</option>
<option value="body" {{ $vm['place'] === 'body' ? ' selected' : '' }}>{{ $lang_messages['select_body'] ?? 'Body' }}</option>
</select>
{{ $lang_messages['text_jump_to'] ?? 'Jump to' }}<select name="box">
{!! $vm['jumpToBoxes'] !!}
</select> <input class=btn type="submit" value={{ $lang_messages['submit_go'] ?? 'Go' }}></form>
