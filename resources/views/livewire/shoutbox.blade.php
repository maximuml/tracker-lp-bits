<section class="nx-idx-card">
<h2>
    <a href="#" wire:click.prevent="toggle" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="kshoutbox"><img class="{{ $open ? 'minus' : 'plus' }}" src="pic/trans.gif" alt="Show/Hide" title="{{ $showHideTitle }}" /></a>
    {{ $cardTitle }} - <span class="small">{{ $autoRefreshLabel }}</span>
    <span class="striking" id="countdown" wire:ignore>{{ $refreshSeconds }}</span><span class="small">{{ $secondsLabel }}</span>
    - <a href="/web/shoutbox_history" class="small">{{ $historyLabel }}</a>
    @if($canManage)
        - <span class="small" id="clear-shout-box" data-confirm="{{ $clearConfirm }}" wire:ignore>[<a class="altlink" href="#"><b>{{ $clearLabel }}</b></a>]</span>
    @endif
    <button type="button" class="nx-shoutbox-mentions" id="shoutbox-mentions" hidden wire:ignore></button>
</h2>
<div id="kshoutbox" class="p-[10pt] @if(!$open) nx-hidden @endif">
<div wire:poll.{{ $refresh }}s>
    <x-data-table :caption="__('index.text_shoutbox')" captionHidden>
    @foreach ($items as $item)
        <tr><td class="{{ $item['rowClass'] }}"><span class='date'>[{{ $item['time'] }}]</span> @if ($item['actions'] !== null)<x-shoutbox.actions :msg-id="$item['actions']['msgId']" :can-edit="$item['actions']['canEdit']" :can-delete="$item['actions']['canDelete']" />@endif <x-shoutbox.avatar :url="$item['avatarUrl']" :user-id="$item['avatarUserId']" :tooltip="$item['avatarTooltip']" :spacer="$item['avatarSpacer']" /> @if ($item['classBadge'] !== null)<x-shoutbox.class-badge :modifier="$item['classBadge']['modifier']" :tooltip="$item['classBadge']['tooltip']" :label="$item['classBadge']['label']" />@endif@if (! empty($item['isGuest']))<b>{{ $item['username'] }}</b>@else{{ $item['username'] }}@endif @if ($item['reactions'] !== null)<x-shoutbox.reactions :shout-id="$item['reactions']['shoutId']" :reactions="$item['reactions']['reactions']" :show-picker="$item['reactions']['showPicker']" :picker-emojis="$item['reactions']['pickerEmojis']" />@endif <x-shoutbox.message :id="$item['msgId']" :is-long="$item['msgLong']" :raw="$item['msgRaw']" :formatted="$item['msgFormatted']" :edited-time="$item['editedTime']" :label-more="$item['labelMore']" :label-less="$item['labelLess']" />
</td></tr>
    @endforeach
    </x-data-table>
    @if ($status !== '')
        <p>{{ $status }}</p>
    @endif
    <form wire:submit="send" name="shbox" method="post" action="#">
        {{ $toolbar }}
        <div class="nx-flex">
            <label for="shbox_text">{{ __('index.text_message') }}</label>
            <input type="text" name="shbox_text" id="shbox_text" wire:model="text" class="grow" maxlength="1000" autocomplete="off" />
            <button type="submit" id="hbsubmit" class="nx-btn nx-btn--primary">{{ __('index.sumbit_shout') }}</button>
            <button type="button" class="nx-btn nx-btn--primary" wire:click="$set('text', '')">{{ __('index.submit_clear') }}</button>
        </div>
    </form>
</div>
</div>
</section>
