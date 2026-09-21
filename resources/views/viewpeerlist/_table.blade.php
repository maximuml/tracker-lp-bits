<b>{{ $table->count }} {{ $table->name }}</b>
@if ($table->count > 0)
<table data-nx="data" class="main">
<tr>
    <th class="colhead nx-w-1p" scope="col">{{ __('legacy/viewpeerlist.col_user_ip') }}</th>
    @if ($table->showLocationColumn)
        <th class="colhead" scope="col">{{ __('legacy/viewpeerlist.col_location') }}</th>
    @endif
    <th class="colhead nx-w-1p" scope="col">{{ __('legacy/viewpeerlist.col_connectable') }}</th>
    <th class="colhead nx-w-1p" scope="col">{{ __('legacy/viewpeerlist.col_uploaded') }}</th>
    <th class="colhead nx-w-1p" scope="col">{{ __('legacy/viewpeerlist.col_rate') }}</th>
    <th class="colhead nx-w-1p" scope="col">{{ __('legacy/viewpeerlist.col_downloaded') }}</th>
    <th class="colhead nx-w-1p" scope="col">{{ __('legacy/viewpeerlist.col_rate') }}</th>
    <th class="colhead nx-w-1p" scope="col">{{ __('legacy/viewpeerlist.col_ratio') }}</th>
    <th class="colhead nx-w-1p" scope="col">{{ __('legacy/viewpeerlist.col_complete') }}</th>
    <th class="colhead nx-w-1p" scope="col">{{ __('legacy/viewpeerlist.col_connected') }}</th>
    <th class="colhead nx-w-1p" scope="col">{{ __('legacy/viewpeerlist.col_idle') }}</th>
    <th class="colhead nx-w-1p" scope="col">{{ __('legacy/viewpeerlist.col_client') }}</th>
</tr>
@foreach ($table->rows as $row)
<tr @if ($row->highlighted) bgcolor="#BBAF9B" @endif>
    <td class="rowfollow nx-w-1p">
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
        <td class="rowfollow nx-w-1p"><div class="nx-flex">
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
    <td class="rowfollow nx-center nx-w-1p"><nobr>@if ($row->connectableYes){{ __('legacy/viewpeerlist.text_yes') }}@else<span class="nx-color-red">{{ __('legacy/viewpeerlist.text_no') }}</span>@endif</nobr></td>
    <td class="rowfollow nx-center nx-w-1p"><nobr>{{ $row->uploaded }}</nobr></td>
    <td class="rowfollow nx-center nx-w-1p"><nobr>{{ $row->uploadRate }}/s</nobr></td>
    <td class="rowfollow nx-center nx-w-1p"><nobr>{{ $row->downloaded }}</nobr></td>
    <td class="rowfollow nx-center nx-w-1p"><nobr>{{ $row->downloadRate }}/s</nobr></td>
    <td class="rowfollow nx-center nx-w-1p">@if ($row->ratioClass !== null)<span class="{{ $row->ratioClass }}"><nobr>{{ $row->ratioText }}</nobr></span>@else{{ $row->ratioText }}@endif</td>
    <td class="rowfollow nx-center nx-w-1p"><nobr>{{ $row->completePercent }}</nobr></td>
    <td class="rowfollow nx-center nx-w-1p"><nobr>{{ $row->connected }}</nobr></td>
    <td class="rowfollow nx-center nx-w-1p"><nobr>{{ $row->idle }}</nobr></td>
    <td class="rowfollow nx-center nx-w-1p"><nobr>{{ $row->client }}</nobr></td>
</tr>
@endforeach
</table>
@endif
