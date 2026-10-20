<tr>
    <td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('offers.row_vote') }}</td>
    <td class="align-top px-2.5 py-1.5">
        @if ($error !== '')
            <span class="text-nxm-danger">{{ $error }}</span>&nbsp;
        @elseif (! $alreadyVoted)
            <b><button type="button" wire:click="vote('yeah')" class="nx-btn-link"><span class="text-nxm-success">{{ __('offers.text_for') }}</span></button></b>@if ($canAgainst) - <b><button type="button" wire:click="vote('against')" class="nx-btn-link"><span class="text-nxm-danger">{{ __('offers.text_against') }}</span></button></b>@endif&nbsp;
        @else
            <i>{{ __('offers.std_already_voted') }}</i>&nbsp;
        @endif
        <b>{{ __('offers.text_for') }}:</b> {{ $yeah }} <b>{{ __('offers.text_against') }}:</b> {{ $against }} &nbsp; &nbsp; <a href="{{ request()->getPathInfo() }}?id={{ $offerId }}&amp;offer_vote=1"><i>{{ __('offers.text_see_vote_detail') }}</i></a>
    </td>
</tr>
