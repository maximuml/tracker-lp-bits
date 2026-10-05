<b>{{ $table->count }} {{ $table->name }}</b>
@if ($table->count > 0)
<x-data-table class="main" :caption="$table->name" :caption-hidden="true">
    <x-slot:head>
        <thead>
            <tr>
                <th class="w-[1%]" scope="col">{{ __('legacy/viewpeerlist.col_user_ip') }}</th>
                @if ($table->showLocationColumn)
                    <th scope="col">{{ __('legacy/viewpeerlist.col_location') }}</th>
                @endif
                <th class="w-[1%]" scope="col">{{ __('legacy/viewpeerlist.col_connectable') }}</th>
                <th class="w-[1%]" scope="col">{{ __('legacy/viewpeerlist.col_uploaded') }}</th>
                <th class="w-[1%]" scope="col">{{ __('legacy/viewpeerlist.col_rate') }}</th>
                <th class="w-[1%]" scope="col">{{ __('legacy/viewpeerlist.col_downloaded') }}</th>
                <th class="w-[1%]" scope="col">{{ __('legacy/viewpeerlist.col_rate') }}</th>
                <th class="w-[1%]" scope="col">{{ __('legacy/viewpeerlist.col_ratio') }}</th>
                <th class="w-[1%]" scope="col">{{ __('legacy/viewpeerlist.col_complete') }}</th>
                <th class="w-[1%]" scope="col">{{ __('legacy/viewpeerlist.col_connected') }}</th>
                <th class="w-[1%]" scope="col">{{ __('legacy/viewpeerlist.col_idle') }}</th>
                <th class="w-[1%]" scope="col">{{ __('legacy/viewpeerlist.col_client') }}</th>
            </tr>
        </thead>
    </x-slot:head>
    @foreach ($table->rows as $row)
        <tr @if ($row->highlighted) class="bg-[#BBAF9B]" @endif>
            <td class="w-[1%]">
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
                <td class="w-[1%]"><div class="flex">
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
            <td class="w-[1%] whitespace-nowrap text-center">@if ($row->connectableYes){{ __('legacy/viewpeerlist.text_yes') }}@else<span class="text-nxm-danger">{{ __('legacy/viewpeerlist.text_no') }}</span>@endif</td>
            <td class="w-[1%] whitespace-nowrap text-center">{{ $row->uploaded }}</td>
            <td class="w-[1%] whitespace-nowrap text-center">{{ $row->uploadRate }}/s</td>
            <td class="w-[1%] whitespace-nowrap text-center">{{ $row->downloaded }}</td>
            <td class="w-[1%] whitespace-nowrap text-center">{{ $row->downloadRate }}/s</td>
            <td class="w-[1%] whitespace-nowrap text-center">@if ($row->ratioClass !== null)<span class="{{ $row->ratioClass }}"><span class="whitespace-nowrap">{{ $row->ratioText }}</span></span>@else{{ $row->ratioText }}@endif</td>
            <td class="w-[1%] whitespace-nowrap text-center">{{ $row->completePercent }}</td>
            <td class="w-[1%] whitespace-nowrap text-center">{{ $row->connected }}</td>
            <td class="w-[1%] whitespace-nowrap text-center">{{ $row->idle }}</td>
            <td class="w-[1%] whitespace-nowrap text-center">{{ $row->client }}</td>
        </tr>
    @endforeach
</x-data-table>
@endif
