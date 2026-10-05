<div class="contents">
    <div class="nxm-searchpanel__toggle"><button type="button" class="nxm-linklike" wire:click="toggle" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="ksearchboxmain"><img class="{{ $open ? 'minus' : 'plus' }}" src="pic/trans.gif" alt="" aria-hidden="true" />{{ __('legacy/torrents.text_search_box')}}</button></div>
    <div id="ksearchboxmain" class="nxm-searchpanel__body @if(!$open) nx-hidden @endif"><div class="contents" wire:ignore>{{ $slot }}</div></div>
</div>
