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
