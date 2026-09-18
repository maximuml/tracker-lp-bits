<h1 align="center">{{ __('legacy/messages.text_forward_pm') }}</h1>
<form action="/takemessage" method="post">
@csrf
<input type="hidden" name="forward" value="1">
<input type="hidden" name="origmsg" value="{{ $forward['pmId'] }}">
<div class="nx-fgrid nx-fgrid--flat">
<div class="nx-fhead">{{ __('legacy/messages.row_to') }}</div>
<div class="nx-fcell"><input type="text" name="to" style="width: 200px"></div>
<div class="nx-fhead">{{ __('legacy/messages.row_original_receiver') }}</div>
<div class="nx-fcell">{{ $forward['fromName'] ?? '' }}</div>
<div class="nx-fhead">{{ __('legacy/messages.row_original_sender') }}</div>
<div class="nx-fcell">{{ $forward['origName'] ?? '' }}</div>
<div class="nx-fhead">{{ __('legacy/messages.row_subject') }}</div>
<div class="nx-fcell"><input type="text" name="subject" value="{{ $forward['subject'] }}" style="width: 500px"></div>
<div class="nx-fhead"><nobr>{{ __('legacy/messages.row_message') }}</nobr></div>
<div class="nx-fcell"><textarea name="body" style="width: 500px" rows="8"></textarea><br />{{ $forward['body'] ?? '' }}</div>
<div class="nx-ffull nx-center"><input class=checkbox type="checkbox" name="save" value="yes"{{ \App\Support\LegacyYesNo::isYes($curUser['savepms'] ?? null) ? ' checked' : '' }}>{{ __('legacy/messages.checkbox_save_message') }}&nbsp;
<input type="submit" class="btn" value={{ __('legacy/messages.submit_forward') }}></div>
</div>
</form>
