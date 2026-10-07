<div>
    <span @if ($open) class="nx-hidden" @endif><a href="#" wire:click.prevent="show">{{ __('details.text_see_full_list') }}</a></span>
    <span @if (! $open) class="nx-hidden" @endif><a href="#" wire:click.prevent="hide">{{ __('details.text_hide_list') }}</a></span>
    <i wire:loading>Loading...</i>
    @if ($open)
        @include('viewfilelist.index', ['files' => $files, 'CURUSER' => $CURUSER])
    @endif
</div>
