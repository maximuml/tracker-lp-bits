@if($shoutbox->show)
<livewire:shoutbox :card-title="$shoutbox->title" :auto-refresh-label="$shoutbox->autoRefreshLabel" :refresh-seconds="$shoutbox->refreshSeconds" :seconds-label="$shoutbox->secondsLabel" :history-label="$shoutbox->historyLabel" :can-manage="$shoutbox->canManage" :clear-confirm="$shoutbox->clearConfirm" :clear-label="$shoutbox->clearLabel" :show-hide-title="$shoutbox->showHideTitle" />
@endif
