<b>{{ $table->count }} {{ $table->name }}</b>
@if ($table->count > 0)
<table data-nx="data" width="100%" class="main" border="1" cellspacing="0" cellpadding="3">
<tr>
    <td class="colhead" align="center" width="1%">{{ __('legacy/viewpeerlist.col_user_ip') }}</td>
    @if ($table->showLocationColumn)
        <td class="colhead" align="center">{{ __('legacy/viewpeerlist.col_location') }}</td>
    @endif
    <td class="colhead" align="center" width="1%">{{ __('legacy/viewpeerlist.col_connectable') }}</td>
    <td class="colhead" align="center" width="1%">{{ __('legacy/viewpeerlist.col_uploaded') }}</td>
    <td class="colhead" align="center" width="1%">{{ __('legacy/viewpeerlist.col_rate') }}</td>
    <td class="colhead" align="center" width="1%">{{ __('legacy/viewpeerlist.col_downloaded') }}</td>
    <td class="colhead" align="center" width="1%">{{ __('legacy/viewpeerlist.col_rate') }}</td>
    <td class="colhead" align="center" width="1%">{{ __('legacy/viewpeerlist.col_ratio') }}</td>
    <td class="colhead" align="center" width="1%">{{ __('legacy/viewpeerlist.col_complete') }}</td>
    <td class="colhead" align="center" width="1%">{{ __('legacy/viewpeerlist.col_connected') }}</td>
    <td class="colhead" align="center" width="1%">{{ __('legacy/viewpeerlist.col_idle') }}</td>
    <td class="colhead" align="center" width="1%">{{ __('legacy/viewpeerlist.col_client') }}</td>
</tr>
@foreach ($table->rows as $row)
<tr @if ($row->highlighted) bgcolor="#BBAF9B" @endif>
    <td class="rowfollow" align="left" width="1%">
        @if ($row->anonymous)
            <i>{{ __('legacy/viewpeerlist.text_anonymous') }}</i>
            @if ($row->revealUsername)
                <br />({{ $row->username }})
            @endif
        @else
            {{ $row->username }}
        @endif
    </td>
    @if ($table->showLocationColumn)
        <td class="rowfollow" align="left" width="1%"><div class="nx-flex">
            @if ($row->revealLocation)
                @if ($row->locationTitle !== null)
                    <div title="{{ $row->locationTitle }}">@foreach ($row->locationLines as $line){{ $line }}@if (! $loop->last)<br/>@endif @endforeach</div>
                @else
                    <div>@foreach ($row->locationLines as $line){{ $line }}@if (! $loop->last)<br/>@endif @endforeach</div>
                @endif
            @endif
            @if ($row->anonymous)
                <div><i>{{ __('legacy/viewpeerlist.text_anonymous') }}</i></div>
            @endif
        </div></td>
    @endif
    <td class="rowfollow" align="center" width="1%"><nobr>@if ($row->connectableYes){{ __('legacy/viewpeerlist.text_yes') }}@else<span class="nx-color-red">{{ __('legacy/viewpeerlist.text_no') }}</span>@endif</nobr></td>
    <td class="rowfollow" align="center" width="1%"><nobr>{{ $row->uploaded }}</nobr></td>
    <td class="rowfollow" align="center" width="1%"><nobr>{{ $row->uploadRate }}/s</nobr></td>
    <td class="rowfollow" align="center" width="1%"><nobr>{{ $row->downloaded }}</nobr></td>
    <td class="rowfollow" align="center" width="1%"><nobr>{{ $row->downloadRate }}/s</nobr></td>
    <td class="rowfollow" align="center" width="1%">@if ($row->ratioClass !== null)<span class="{{ $row->ratioClass }}"><nobr>{{ $row->ratioText }}</nobr></span>@else{{ $row->ratioText }}@endif</td>
    <td class="rowfollow" align="center" width="1%"><nobr>{{ $row->completePercent }}</nobr></td>
    <td class="rowfollow" align="center" width="1%"><nobr>{{ $row->connected }}</nobr></td>
    <td class="rowfollow" align="center" width="1%"><nobr>{{ $row->idle }}</nobr></td>
    <td class="rowfollow" align="center" width="1%"><nobr>{{ $row->client }}</nobr></td>
</tr>
@endforeach
</table>
@endif
