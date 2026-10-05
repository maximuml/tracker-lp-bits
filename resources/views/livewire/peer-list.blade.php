<tr>
    <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">
        {{ __('legacy/details.row_peers') }}<br />
        <span @if ($open) class="nx-hidden" @endif><a href="#" wire:click.prevent="show" class="sublink">{{ __('legacy/details.text_see_full_list') }}</a></span>
        <span @if (! $open) class="nx-hidden" @endif><a href="#" wire:click.prevent="hide" class="sublink">{{ __('legacy/details.text_hide_list') }}</a></span>
    </td>
    <td class="align-top px-2.5 py-1.5">
        <div @if ($open) class="nx-hidden" @endif><b>{{ $seeders }}{{ __('legacy/details.text_seeders') }}{{ \App\Support\Strings::addS($seeders) }}</b> | <b>{{ $leechers }}{{ __('legacy/details.text_leechers') }}{{ \App\Support\Strings::addS($leechers) }}</b></div>
        <i wire:loading>Loading...</i>
        @if ($open)
            @include('viewpeerlist.index', ['seederTable' => $seederTable, 'leecherTable' => $leecherTable])
        @endif
    </td>
</tr>
