@if($shoutbox['show'])
<h2>
    {{ $shoutbox['title'] }} - <font class="small">{{ $shoutbox['autoRefreshLabel'] }}</font>
    <font class='striking' id="countdown"></font><font class="small">{{ $shoutbox['secondsLabel'] }}</font>
    - <a href="shoutbox_history.php" class="small">{{ $shoutbox['historyLabel'] }}</a>
    @if($shoutbox['canManage'])
        - <font class="small" id="clear-shout-box">[<a class="altlink" href="javascript:;"><b>{{ $shoutbox['clearLabel'] }}</b></a>]</font>
    @endif
</h2>
<table width="100%"><tr><td class="text">
<iframe id='iframe-shout-box' src='shoutbox.php?type=shoutbox' width='100%' height='180' frameborder='0' name='sbox' marginwidth='0' marginheight='0'></iframe><br /><br />
<form action='shoutbox.php' method='get' target='sbox' name='shbox'>
{!! $shoutbox['toolbar'] !!}
<div style="display: flex">
<label for='shbox_text'>{{ $shoutbox['messageLabel'] }}</label><input type='text' name='shbox_text' id='shbox_text' size='100' style='flex-grow: 1; border: 1px solid gray;' />  <input type='submit' id='hbsubmit' class='btn' name='shout' value="{{ $shoutbox['submitLabel'] }}" />
<input type='reset' class='btn' value="{{ $shoutbox['clearButtonLabel'] }}" /> <input type='hidden' name='sent' value='yes' /><input type='hidden' name='type' value='shoutbox' />
</div>
</form></td></tr></table>
@endif
