<form action="/messages" method="get">
<input type="hidden" name="action" value="viewmailbox"><label for="searchinput">{{ __('legacy/messages.text_search') }}</label>&nbsp;&nbsp;<input id="searchinput" name="keyword" type="text" value="{{ $viewmailbox['keyword'] }}"/>
<label>{{ __('legacy/messages.text_in') }}&nbsp;<select name="place">
<option value="both" {{ $viewmailbox['place'] === 'both' ? ' selected' : '' }}>{{ __('legacy/messages.select_both') }}</option>
<option value="title" {{ $viewmailbox['place'] === 'title' ? ' selected' : '' }}>{{ __('legacy/messages.select_title') }}</option>
<option value="body" {{ $viewmailbox['place'] === 'body' ? ' selected' : '' }}>{{ __('legacy/messages.select_body') }}</option>
</select></label>
<label>{{ __('legacy/messages.text_jump_to') }}<select name="box">
@foreach ($viewmailbox['jumpToBoxes'] as $opt)
<option value="{{ $opt->value }}"{{ $opt->selected ? ' selected' : '' }}>{{ $opt->label }}</option>
@endforeach
</select></label> <input class=btn type="submit" value={{ __('legacy/messages.submit_go') }}></form>
