<section class="nx-idx-card">
<h2>
    <a href="#" wire:click.prevent="toggle" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="kshoutbox"><i class="{{ $open ? 'minus' : 'plus' }}" role="img" aria-label="Show/Hide" title="{{ $showHideTitle }}"></i></a>
    {{ $cardTitle }} - <span class="small">{{ $autoRefreshLabel }}</span>
    <span class="striking" id="countdown" wire:ignore>{{ $refreshSeconds }}</span><span class="small">{{ $secondsLabel }}</span>
    - <a href="/web/shoutbox_history" class="small">{{ $historyLabel }}</a>
    @if($canManage)
        - <span class="small" id="clear-shout-box">[<a class="altlink" href="#" wire:click.prevent="confirmClear"><b>{{ $clearLabel }}</b></a>]</span>
    @endif
    <button type="button" class="nx-shoutbox-mentions" id="shoutbox-mentions" hidden wire:ignore></button>
</h2>
<div id="kshoutbox" class="p-[10pt] @if(!$open) nx-hidden @endif">
<div wire:poll.visible.{{ $refresh }}s>
    <x-data-table :caption="__('index.text_shoutbox')" captionHidden>
    @foreach ($items as $item)
        <tr><td class="{{ $item['rowClass'] }}"><span class='date'>[{{ $item['time'] }}]</span> @if ($item['actions'] !== null)<span class="shout-actions">@if ($item['actions']['canEdit']) <a href="#" class="shout-action-edit" wire:click.prevent="startEdit({{ $item['actions']['msgId'] }})" title="{{ __('shoutbox.title_edit_shout') }}">[{{ __('shoutbox.text_edit') }}]</a>@endif
@if ($item['actions']['canDelete']) <a href="#" class="shout-action-del" data-shout-del wire:click.prevent="confirmDelete({{ $item['actions']['msgId'] }})" title="{{ __('shoutbox.title_delete_shout') }}">[{{ __('shoutbox.text_del') }}]</a>@endif</span>@endif <x-shoutbox.avatar :url="$item['avatarUrl']" :user-id="$item['avatarUserId']" :tooltip="$item['avatarTooltip']" :spacer="$item['avatarSpacer']" /> @if ($item['classBadge'] !== null)<x-shoutbox.class-badge :modifier="$item['classBadge']['modifier']" :tooltip="$item['classBadge']['tooltip']" :label="$item['classBadge']['label']" />@endif@if (! empty($item['isGuest']))<b>{{ $item['username'] }}</b>@else{{ $item['username'] }}@endif @if ($item['reactions'] !== null)<span class="shout-reactions">@foreach ($item['reactions']['reactions'] as $r)<button type="button" class="shout-reaction{{ $r['active'] ? ' active' : '' }}" wire:click.prevent="react({{ $item['reactions']['shoutId'] }}, @js($r['emoji']))" title="{{ $r['title'] }}">{{ $r['emoji'] }} {{ $r['count'] }}</button>@endforeach
@if ($item['reactions']['showPicker'])<button type="button" class="shout-reaction shout-reaction-add" wire:click.prevent="togglePicker({{ $item['reactions']['shoutId'] }})" title="{{ __('shoutbox.title_add_reaction') }}">+</button><span class="shout-reaction-picker{{ $openPickerId === $item['reactions']['shoutId'] ? '' : ' nx-hidden' }}" id="shout-reaction-picker-{{ $item['reactions']['shoutId'] }}">@foreach ($item['reactions']['pickerEmojis'] as $emoji)<button type="button" class="shout-reaction" wire:click.prevent="react({{ $item['reactions']['shoutId'] }}, @js($emoji))" title="{{ __('shoutbox.title_react') }}">{{ $emoji }}</button>@endforeach</span>@endif</span>@endif @if ($editingId === $item['msgId'])<span class="shoutbox-editing"><input type="text" wire:model="editText" wire:keydown.enter.prevent="saveEdit" wire:keydown.escape="cancelEdit" /><button type="button" class="nx-btn nx-btn--primary" wire:click="saveEdit">{{ __('label.save') }}</button><button type="button" class="nx-btn nx-btn--primary" wire:click="cancelEdit">{{ __('label.cancel') }}</button></span>@else<x-shoutbox.message :id="$item['msgId']" :is-long="$item['msgLong']" :raw="$item['msgRaw']" :formatted="$item['msgFormatted']" :edited-time="$item['editedTime']" :label-more="$item['labelMore']" :label-less="$item['labelLess']" />@endif
</td></tr>
    @endforeach
    </x-data-table>
    @if ($status !== '')
        <p>{{ $status }}</p>
    @endif
    <form wire:submit="send" name="shbox" method="post" action="#">
        <div wire:ignore>{{ $toolbar }}</div>
        <div class="nx-flex">
            <label for="shbox_text">{{ __('index.text_message') }}</label>
            <input type="text" name="shbox_text" id="shbox_text" wire:model="text" class="grow" maxlength="1000" autocomplete="off" />
            <button type="submit" id="hbsubmit" class="nx-btn nx-btn--primary">{{ __('index.sumbit_shout') }}</button>
            <button type="button" class="nx-btn nx-btn--primary" wire:click="$set('text', '')">{{ __('index.submit_clear') }}</button>
        </div>
    </form>
</div>
</div>
@if ($confirmingDeleteId !== null)
<dialog class="nx-modal" wire:key="shoutbox-delete-confirm" wire:keydown.escape.window="cancelConfirm">
    <div class="nx-modal__box">
        <div class="nx-modal__header"><h2 class="nx-modal__title">{{ __('functions.text_info') }}</h2><button type="button" class="nx-modal__close" wire:click="cancelConfirm" aria-label="{{ __('functions.text_close') }}">×</button></div>
        <div class="nx-modal__body">{{ __('shoutbox.js_confirm_delete') }}</div>
        <div class="nx-modal__btns nx-modal__btns--center">
            <button type="button" class="nx-modal__btn" wire:click="delete({{ $confirmingDeleteId }})">{{ __('functions.text_yes') }}</button>
            <button type="button" class="nx-modal__btn" wire:click="cancelConfirm">{{ __('label.cancel') }}</button>
        </div>
    </div>
</dialog>
@endif
@if ($confirmingClear)
<dialog class="nx-modal" wire:key="shoutbox-clear-confirm" wire:keydown.escape.window="cancelConfirm">
    <div class="nx-modal__box">
        <div class="nx-modal__header"><h2 class="nx-modal__title">{{ __('functions.text_info') }}</h2><button type="button" class="nx-modal__close" wire:click="cancelConfirm" aria-label="{{ __('functions.text_close') }}">×</button></div>
        <div class="nx-modal__body">{{ $clearConfirm }}</div>
        <div class="nx-modal__btns nx-modal__btns--center">
            <button type="button" class="nx-modal__btn" wire:click="clear">{{ __('functions.text_yes') }}</button>
            <button type="button" class="nx-modal__btn" wire:click="cancelConfirm">{{ __('label.cancel') }}</button>
        </div>
    </div>
</dialog>
@endif
</section>
