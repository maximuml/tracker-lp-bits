@extends('layouts.app')

@section('title', __('location.page_title'))

@section('content')
<x-page :title="'Manage Locations'" width="list">
<x-frame>
@if ($vm->error ?? '')
    <p><strong>{{ $vm->error }}</strong></p>
@endif

@if (($vm->mode ?? '') === 'edit' && ! empty($vm->editRow))
<form name='form1' method='post' action='/web/system/location'>@csrf
<input type='hidden' name='id' value='{{ (int) $vm->editRow['id'] }}'>
<input type='hidden' name='edited' value='1'>
<div class="nx-fgrid nx-fgrid--flat nx-main w-[50%]">
<div class="nx-ffull nx-colhead text-center">{{ __('location.editing') }}</div>
<div class="nx-fhead">{{ __('location.col_name') }}:</div><div class="nx-fcell"><input type='text' class="w-full" size=10 name='name' aria-label="{{ __('location.col_name') }}" id='loc-name' value='{{ $vm->editRow['name'] }}'></div>
<label class="nx-fhead" for="loc-location_main"><span class="nx-nobr">{{ __('location.main_location_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=50 name='location_main' id='loc-location_main' value='{{ $vm->editRow['location_main'] }}'></div>
<label class="nx-fhead" for="loc-location_sub"><span class="nx-nobr">{{ __('location.sub_location_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=50 name='location_sub' id='loc-location_sub' value='{{ $vm->editRow['location_sub'] }}'></div>
<label class="nx-fhead" for="loc-start_ip"><span class="nx-nobr">{{ __('location.start_ip_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=30 name='start_ip' id='loc-start_ip' value='{{ $vm->editRow['start_ip'] }}'></div>
<label class="nx-fhead" for="loc-end_ip"><span class="nx-nobr">{{ __('location.end_ip_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=30 name='end_ip' id='loc-end_ip' value='{{ $vm->editRow['end_ip'] }}'></div>
<label class="nx-fhead" for="loc-theory_upspeed"><span class="nx-nobr">{{ __('location.theory_up_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='theory_upspeed' id='loc-theory_upspeed' value='{{ $vm->editRow['theory_upspeed'] }}'></div>
<label class="nx-fhead" for="loc-theory_downspeed"><span class="nx-nobr">{{ __('location.theory_down_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='theory_downspeed' id='loc-theory_downspeed' value='{{ $vm->editRow['theory_downspeed'] }}'></div>
<label class="nx-fhead" for="loc-practical_upspeed"><span class="nx-nobr">{{ __('location.practical_up_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='practical_upspeed' id='loc-practical_upspeed' value='{{ $vm->editRow['practical_upspeed'] }}'></div>
<label class="nx-fhead" for="loc-practical_downspeed"><span class="nx-nobr">{{ __('location.practical_down_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='practical_downspeed' id='loc-practical_downspeed' value='{{ $vm->editRow['practical_downspeed'] }}'></div>
<div class="nx-fhead">{{ __('location.col_pic') }}:</div><div class="nx-fcell"><input type='text' class="w-full" size=50 name='flagpic' aria-label="{{ __('location.col_pic') }}" id='loc-flagpic' value='{{ $vm->editRow['flagpic'] }}'></div>
<div class="nx-ffull nx-toolbox text-center"><input class='nx-btn nx-btn--primary' type='Submit'></div>
</div>
</form>
@else
<div class="flex flex-col md:flex-row items-start nx-row--spread">
<form name='form1' method='post' action='/web/system/location' class="w-full md:w-[48%]">@csrf
<div class="nx-fgrid nx-fgrid--flat nx-main">
<div class="nx-ffull nx-colhead text-center">{{ __('location.add_new') }}</div>
<div class="nx-fhead">{{ __('location.col_name') }}:</div><div class="nx-fcell"><input type='text' class="w-full" size=10 name='name' aria-label="{{ __('location.col_name') }}"></div>
<label class="nx-fhead" for="loc-location_main"><span class="nx-nobr">{{ __('location.main_location_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=50 name='location_main'></div>
<label class="nx-fhead" for="loc-location_sub"><span class="nx-nobr">{{ __('location.sub_location_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=50 name='location_sub'></div>
<label class="nx-fhead" for="loc-start_ip"><span class="nx-nobr">{{ __('location.start_ip_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=30 name='start_ip'></div>
<label class="nx-fhead" for="loc-end_ip"><span class="nx-nobr">{{ __('location.end_ip_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=30 name='end_ip'></div>
<label class="nx-fhead" for="loc-theory_upspeed"><span class="nx-nobr">{{ __('location.theory_up_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='theory_upspeed'></div>
<label class="nx-fhead" for="loc-theory_downspeed"><span class="nx-nobr">{{ __('location.theory_down_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='theory_downspeed'></div>
<label class="nx-fhead" for="loc-practical_upspeed"><span class="nx-nobr">{{ __('location.practical_up_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='practical_upspeed'></div>
<label class="nx-fhead" for="loc-practical_downspeed"><span class="nx-nobr">{{ __('location.practical_down_colon') }}</span></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='practical_downspeed'></div>
<div class="nx-fhead">{{ __('location.col_pic') }}:</div><div class="nx-fcell"><input type='text' class="w-full" size=50 name='flagpic' aria-label="{{ __('location.col_pic') }}"><input type='hidden' name='add' value='true'></div>
<div class="nx-ffull nx-toolbox text-center"><input class='nx-btn nx-btn--primary' type='Submit'></div>
</div>
</form>

<form name='form2' method='get' action='{{ $vm->actionUrl ?? '' }}' class="w-full md:w-[48%]">
<div class="nx-fgrid nx-fgrid--flat nx-main">
<div class="nx-ffull nx-colhead text-center">{{ __('location.check_ip_range') }}</div>
<div class="nx-fhead"><span class="nx-nobr">{{ __('location.start_ip_colon') }}</span></div><div class="nx-fcell"><input type='text' class="w-full" size=30 name='range_start_ip' aria-label="{{ __('location.col_start_ip') }}" value='{{ $vm->rangeStartIp ?? '' }}'></div>
<div class="nx-fhead"><span class="nx-nobr">{{ __('location.end_ip_colon') }}</span></div><div class="nx-fcell"><input type='text' class="w-full" size=30 name='range_end_ip' aria-label="{{ __('location.col_end_ip') }}" value='{{ $vm->rangeEndIp ?? '' }}'><input type='hidden' name='check_range' value='true'></div>
<div class="nx-ffull nx-toolbox text-center"><input class='nx-btn nx-btn--primary' type='Submit'></div>
</div>
</form>
</div>

@if ($vm->hasRangeFilter ?? false)
    <p><strong>{{ $vm->message ?? '' }}</strong></p>
@else
    <p><strong>{{ ($vm->success ?? false) ? '(Updated!)' : '' }}Existing Locations:</strong></p>
@endif
<x-data-table caption="Manage Locations" captionHidden><x-slot:head><thead><tr>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>ID</b></th>
<th class="bg-nxm-surface-alt font-semibold text-left" scope="col"><b>{{ __('location.col_name') }}</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('location.col_pic') }}</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b><span class="nx-nobr">{{ __('location.col_main') }}</span></b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b><span class="nx-nobr">{{ __('location.col_sub') }}</span></b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('location.col_start_ip') }}</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('location.col_end_ip') }}</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>T.U</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>P.U</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>T.D</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>P.D</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('functions.text_edit') }}</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('functions.text_delete') }}</b></th>
</tr></thead></x-slot:head>
@foreach ($vm->rows ?? [] as $row)
<tr>
<td class="align-top px-2.5 py-1.5 text-center"><strong>{{ (int) $row['id'] }}</strong></td>
<td class="align-top px-2.5 py-1.5"><strong>{{ $row['name'] }}</strong></td>
<td class="align-top px-2.5 py-1.5 text-center">@if ($row['flagpic_url'] ?? '')<img src="{{ $row['flagpic_url'] }}" alt="{{ $row['name'] }}" loading="lazy" decoding="async" />@else-@endif</td>
<td class="align-top px-2.5 py-1.5">{{ $row['location_main'] }}</td>
<td class="align-top px-2.5 py-1.5">{{ $row['location_sub'] }}</td>
<td class="align-top px-2.5 py-1.5">{{ $row['start_ip'] }}</td>
<td class="align-top px-2.5 py-1.5">{{ $row['end_ip'] }}</td>
<td class="align-top px-2.5 py-1.5">{{ $row['theory_upspeed'] }}</td>
<td class="align-top px-2.5 py-1.5">{{ $row['practical_upspeed'] }}</td>
<td class="align-top px-2.5 py-1.5">{{ $row['theory_downspeed'] }}</td>
<td class="align-top px-2.5 py-1.5">{{ $row['practical_downspeed'] }}</td>
<td class="align-top px-2.5 py-1.5 text-center"><a href='{{ $vm->actionUrl ?? '' }}?editid={{ (int) $row['id'] }}'>{{ __('functions.text_edit') }}</a></td>
<td class="align-top px-2.5 py-1.5 text-center"><a href='{{ $vm->actionUrl ?? '' }}?delid={{ (int) $row['id'] }}'>{{ __('location.text_remove') }}</a></td>
</tr>
@endforeach
</x-data-table>
<x-pagination :page="$vm->pager['page']" :pages="$vm->pager['pages']" :href="$vm->pager['href']" :prev-label="$vm->pager['prevLabel']" :next-label="$vm->pager['nextLabel']" :zero-based="$vm->pager['zeroBased']" label="Pagination bottom" />
@endif
</x-frame>
</x-page>
@endsection
