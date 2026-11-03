<form id="compose" name="forward" action="/web/messages/send" method="post" class="nx-compose">
@csrf
<input type="hidden" name="forward" value="1">
<input type="hidden" name="origmsg" value="{{ $forward['pmId'] }}">
<h1 class="nx-compose__title">{{ __('messages.text_forward_pm') }}</h1>
<div class="nx-compose__field">
    <label for="forward-to">{{ __('messages.row_to') }}</label>
    <input type="text" id="forward-to" name="to">
</div>
<div class="nx-compose__field">
    <p class="nx-field__help">{{ __('messages.row_original_receiver') }} {{ $forward['fromName'] ?? '' }} · {{ __('messages.row_original_sender') }} {{ $forward['origName'] ?? '' }}</p>
</div>
<div class="nx-compose__field">
    <label for="forward-subject">{{ __('messages.row_subject') }}</label>
    <input type="text" id="forward-subject" name="subject" value="{{ $forward['subject'] }}">
</div>
<div class="nx-compose__field">
    <label for="forward-body">{{ __('messages.row_message') }}</label>
    <textarea id="forward-body" name="body" rows="8"></textarea>
    <div class="nx-box">{{ $forward['body'] ?? '' }}</div>
</div>
<div class="nx-compose__foot">
    <div class="nx-compose__opts">
        <label><input type="checkbox" name="save" value="yes"{{ \App\Support\YesNo::isYes($curUser['savepms'] ?? null) ? ' checked' : '' }}> {{ __('messages.checkbox_save_message') }}</label>
    </div>
    <input id="qr" type="submit" class="nx-btn nx-btn--primary" value="{{ __('messages.submit_forward') }}">
</div>
</form>
