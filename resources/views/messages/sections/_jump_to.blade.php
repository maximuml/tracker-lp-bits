<form class="nx-mailsearch" action="/messages" method="get">
<input type="hidden" name="action" value="viewmailbox">
<label class="nx-sr-only" for="message-keyword">{{ __('messages.text_search') }}</label>
<input id="message-keyword" name="keyword" type="text" value="{{ $viewmailbox['keyword'] }}" placeholder="{{ __('messages.text_search') }}">
<select id="message-place" name="place" aria-label="{{ __('messages.text_search_in') }}">
<option value="both" {{ $viewmailbox['place'] === 'both' ? ' selected' : '' }}>{{ __('messages.select_both') }}</option>
<option value="title" {{ $viewmailbox['place'] === 'title' ? ' selected' : '' }}>{{ __('messages.select_title') }}</option>
<option value="body" {{ $viewmailbox['place'] === 'body' ? ' selected' : '' }}>{{ __('messages.select_body') }}</option>
</select>
<select id="message-box" name="box" aria-label="{{ __('messages.text_mailboxes') }}">
@foreach ($viewmailbox['jumpToBoxes'] as $opt)
<option value="{{ $opt->value }}"{{ $opt->selected ? ' selected' : '' }}>{{ $opt->label }}</option>
@endforeach
</select>
<button type="submit" class="nx-btn nx-btn--primary">{{ __('messages.submit_go') }}</button>
</form>
