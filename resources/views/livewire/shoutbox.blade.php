<section class="nx-idx-card">
<h2>
    <a href="#" wire:click.prevent="toggle" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="kshoutbox"><img class="{{ $open ? 'minus' : 'plus' }}" src="pic/trans.gif" alt="Show/Hide" title="{{ $showHideTitle }}" /></a>
    {{ $cardTitle }} - <span class="small">{{ $autoRefreshLabel }}</span>
    <span class="striking" id="countdown" wire:ignore>{{ $refreshSeconds }}</span><span class="small">{{ $secondsLabel }}</span>
    - <a href="shoutbox_history.php" class="small">{{ $historyLabel }}</a>
    @if($canManage)
        - <span class="small" id="clear-shout-box" data-confirm="{{ $clearConfirm }}" wire:ignore>[<a class="altlink" href="#"><b>{{ $clearLabel }}</b></a>]</span>
    @endif
    <button type="button" class="nx-shoutbox-mentions" id="shoutbox-mentions" hidden wire:ignore></button>
</h2>
<div id="kshoutbox" class="p-[10pt] @if(!$open) nx-hidden @endif">
<div wire:poll.{{ $refresh }}s>
    <x-data-table :caption="__('legacy/index.text_shoutbox')" captionHidden>
    @foreach ($items as $item)
        <tr><td class="{{ $item['rowClass'] }}"><span class='date'>[{{ $item['time'] }}]</span> {{ $item['actions'] }} @include('shoutbox._avatar', ['url' => $item['avatarUrl'], 'userId' => $item['avatarUserId'], 'tooltip' => $item['avatarTooltip'], 'spacer' => $item['avatarSpacer']]) {{ $item['classBadge'] }}@if (! empty($item['isGuest']))<b>{{ $item['username'] }}</b>@else{{ $item['username'] }}@endif {{ $item['reactions'] }} @include('shoutbox._message', ['id' => $item['msgId'], 'isLong' => $item['msgLong'], 'raw' => $item['msgRaw'], 'formatted' => $item['msgFormatted'], 'editedTime' => $item['editedTime'], 'labelMore' => $item['labelMore'], 'labelLess' => $item['labelLess']])
</td></tr>
    @endforeach
    </x-data-table>
    @if ($status !== '')
        <p>{{ $status }}</p>
    @endif
    <form wire:submit="send" name="shbox" method="post" action="#">
        {{ $toolbar }}
        <div class="nx-flex">
            <label for="shbox_text">{{ __('legacy/index.text_message') }}</label>
            <input type="text" name="shbox_text" id="shbox_text" wire:model="text" class="grow" maxlength="1000" autocomplete="off" />
            <button type="submit" id="hbsubmit" class="btn">{{ __('legacy/index.sumbit_shout') }}</button>
            <button type="button" class="btn" wire:click="$set('text', '')">{{ __('legacy/index.submit_clear') }}</button>
        </div>
    </form>
</div>
</div>
</section>
