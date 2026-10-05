@if($shoutbox->show)
<section class="nx-idx-card">
<h2>
    <a href="#" data-klappe="shoutbox" aria-expanded="true"><img class="minus" src="pic/trans.gif" id="picshoutbox" alt="Show/Hide" title="{{ $shoutbox->showHideTitle }}" /></a>
    {{ $shoutbox->title }} - <span class="small">{{ $shoutbox->autoRefreshLabel }}</span>
    <span class="striking" id="countdown"></span><span class="small">{{ $shoutbox->secondsLabel }}</span>
    - <a href="shoutbox_history.php" class="small">{{ $shoutbox->historyLabel }}</a>
    @if($shoutbox->canManage)
        - <span class="small" id="clear-shout-box" data-confirm="{{ $shoutbox->clearConfirm }}">[<a class="altlink" href="#"><b>{{ $shoutbox->clearLabel }}</b></a>]</span>
    @endif
    <button type="button" class="nx-shoutbox-mentions" id="shoutbox-mentions" hidden></button>
</h2>
<div class="p-[10pt]" id="kshoutbox">
<iframe id='iframe-shout-box' title="Shoutbox" src='shoutbox.php?type=shoutbox' name='sbox'></iframe>
<form action='shoutbox.php' method='get' target='sbox' name='shbox'>
{{ $shoutbox->toolbar }}
<div class="nx-flex">
<label for='shbox_text'>{{ $shoutbox->messageLabel }}</label><input type='text' name='shbox_text' id='shbox_text' class="grow" />  <input type='submit' id='hbsubmit' class='btn' name='shout' value="{{ $shoutbox->submitLabel }}" />
<input type='reset' class='btn' value="{{ $shoutbox->clearButtonLabel }}" /> <input type='hidden' name='sent' value='yes' /><input type='hidden' name='type' value='shoutbox' />
</div>
</form></div>
</section>
@endif
