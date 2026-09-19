@if($shoutbox['show'])
<h2>
    {{ $shoutbox['title'] }} - <span class="small">{{ $shoutbox['autoRefreshLabel'] }}</span>
    <span class="striking" id="countdown"></span><span class="small">{{ $shoutbox['secondsLabel'] }}</span>
    - <a href="shoutbox_history.php" class="small">{{ $shoutbox['historyLabel'] }}</a>
    @if($shoutbox['canManage'])
        - <span class="small" id="clear-shout-box">[<a class="altlink" href="#"><b>{{ $shoutbox['clearLabel'] }}</b></a>]</span>
    @endif
</h2>
<div class="nx-text">
<iframe id='iframe-shout-box' src='shoutbox.php?type=shoutbox' width='100%' height='180' frameborder='0' name='sbox' marginwidth='0' marginheight='0'></iframe><br /><br />
<form action='shoutbox.php' method='get' target='sbox' name='shbox'>
{{ ($shoutbox['toolbar'] ?? '') }}
<div class="nx-flex">
<label for='shbox_text'>{{ $shoutbox['messageLabel'] }}</label><input type='text' name='shbox_text' id='shbox_text' size='100' class="nx-grow" />  <input type='submit' id='hbsubmit' class='btn' name='shout' value="{{ $shoutbox['submitLabel'] }}" />
<input type='reset' class='btn' value="{{ $shoutbox['clearButtonLabel'] }}" /> <input type='hidden' name='sent' value='yes' /><input type='hidden' name='type' value='shoutbox' />
</div>
</form></div>
@endif
