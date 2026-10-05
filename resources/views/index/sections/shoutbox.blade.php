@if($shoutbox->show)
<section class="nx-idx-card">
<h2>
    <a href="#" data-klappe="shoutbox" aria-expanded="true"><img class="minus" src="pic/trans.gif" id="picshoutbox" alt="Show/Hide" title="{{ $shoutbox->showHideTitle }}" /></a>
    {{ $shoutbox->title }} - <span class="small">{{ $shoutbox->autoRefreshLabel }}</span>
    <span class="striking" id="countdown">{{ $shoutbox->refreshSeconds }}</span><span class="small">{{ $shoutbox->secondsLabel }}</span>
    - <a href="shoutbox_history.php" class="small">{{ $shoutbox->historyLabel }}</a>
    @if($shoutbox->canManage)
        - <span class="small" id="clear-shout-box" data-confirm="{{ $shoutbox->clearConfirm }}">[<a class="altlink" href="#"><b>{{ $shoutbox->clearLabel }}</b></a>]</span>
    @endif
    <button type="button" class="nx-shoutbox-mentions" id="shoutbox-mentions" hidden></button>
</h2>
<div class="p-[10pt]" id="kshoutbox">
<livewire:shoutbox />
</div>
</section>
@endif
