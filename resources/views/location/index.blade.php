@extends('layouts.app')

@section('title', 'Manage Locations')

@section('content')
<x-page :title="'Manage Locations'" width="list">
<x-frame>
@if ($error ?? '')
    <p><strong>{{ $error }}</strong></p>
@endif

@if (($mode ?? '') === 'edit' && ! empty($editRow))
<form name='form1' method='post' action='/web/system/location'>@csrf
<input type='hidden' name='id' value='{{ (int) $editRow['id'] }}'>
<input type='hidden' name='edited' value='1'>
<div class="nx-fgrid nx-fgrid--flat nx-main w-[50%]">
<div class="nx-ffull nx-colhead text-center">Editing Locations</div>
<div class="nx-fhead">Name:</div><div class="nx-fcell"><input type='text' class="w-full" size=10 name='name' aria-label='Name' id='loc-name' value='{{ $editRow['name'] }}'></div>
<label class="nx-fhead" for="loc-location_main"><nobr>Main Location:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=50 name='location_main' aria-label='Main Location' id='loc-location_main' value='{{ $editRow['location_main'] }}'></div>
<label class="nx-fhead" for="loc-location_sub"><nobr>Sub Location:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=50 name='location_sub' aria-label='Sub Location' id='loc-location_sub' value='{{ $editRow['location_sub'] }}'></div>
<label class="nx-fhead" for="loc-start_ip"><nobr>Start IP:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=30 name='start_ip' aria-label='Start IP' id='loc-start_ip' value='{{ $editRow['start_ip'] }}'></div>
<label class="nx-fhead" for="loc-end_ip"><nobr>End IP:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=30 name='end_ip' aria-label='End IP' id='loc-end_ip' value='{{ $editRow['end_ip'] }}'></div>
<label class="nx-fhead" for="loc-theory_upspeed"><nobr>Theory Up:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='theory_upspeed' aria-label='Theory Up' id='loc-theory_upspeed' value='{{ $editRow['theory_upspeed'] }}'></div>
<label class="nx-fhead" for="loc-theory_downspeed"><nobr>Theory Down:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='theory_downspeed' aria-label='Theory Down' id='loc-theory_downspeed' value='{{ $editRow['theory_downspeed'] }}'></div>
<label class="nx-fhead" for="loc-practical_upspeed"><nobr>Practical Up:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='practical_upspeed' aria-label='Practical Up' id='loc-practical_upspeed' value='{{ $editRow['practical_upspeed'] }}'></div>
<label class="nx-fhead" for="loc-practical_downspeed"><nobr>Practical Down:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='practical_downspeed' aria-label='Practical Down' id='loc-practical_downspeed' value='{{ $editRow['practical_downspeed'] }}'></div>
<div class="nx-fhead">Picture:</div><div class="nx-fcell"><input type='text' class="w-full" size=50 name='flagpic' aria-label='Picture' id='loc-flagpic' value='{{ $editRow['flagpic'] }}'></div>
<div class="nx-ffull nx-toolbox text-center"><input class='nx-btn nx-btn--primary' type='Submit'></div>
</div>
</form>
@else
<div class="flex flex-col md:flex-row items-start nx-row--spread">
<form name='form1' method='post' action='/web/system/location' class="w-full md:w-[48%]">@csrf
<div class="nx-fgrid nx-fgrid--flat nx-main">
<div class="nx-ffull nx-colhead text-center">Add New Locations</div>
<div class="nx-fhead">Name:</div><div class="nx-fcell"><input type='text' class="w-full" size=10 name='name' aria-label='Name'></div>
<label class="nx-fhead" for="loc-location_main"><nobr>Main Location:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=50 name='location_main' aria-label='Main Location'></div>
<label class="nx-fhead" for="loc-location_sub"><nobr>Sub Location:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=50 name='location_sub' aria-label='Sub Location'></div>
<label class="nx-fhead" for="loc-start_ip"><nobr>Start IP:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=30 name='start_ip' aria-label='Start IP'></div>
<label class="nx-fhead" for="loc-end_ip"><nobr>End IP:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=30 name='end_ip' aria-label='End IP'></div>
<label class="nx-fhead" for="loc-theory_upspeed"><nobr>Theory Up:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='theory_upspeed' aria-label='Theory Up'></div>
<label class="nx-fhead" for="loc-theory_downspeed"><nobr>Theory Down:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='theory_downspeed' aria-label='Theory Down'></div>
<label class="nx-fhead" for="loc-practical_upspeed"><nobr>Practical Up:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='practical_upspeed' aria-label='Practical Up'></div>
<label class="nx-fhead" for="loc-practical_downspeed"><nobr>Practical Down:</nobr></label><div class="nx-fcell"><input type='text' class="w-full" size=10 name='practical_downspeed' aria-label='Practical Down'></div>
<div class="nx-fhead">Picture:</div><div class="nx-fcell"><input type='text' class="w-full" size=50 name='flagpic' aria-label='Picture'><input type='hidden' name='add' value='true'></div>
<div class="nx-ffull nx-toolbox text-center"><input class='nx-btn nx-btn--primary' type='Submit'></div>
</div>
</form>

<form name='form2' method='get' action='{{ $actionUrl ?? '' }}' class="w-full md:w-[48%]">
<div class="nx-fgrid nx-fgrid--flat nx-main">
<div class="nx-ffull nx-colhead text-center">Check IP Range</div>
<div class="nx-fhead"><nobr>Start IP:</nobr></div><div class="nx-fcell"><input type='text' class="w-full" size=30 name='range_start_ip' aria-label='Range start IP' value='{{ $rangeStartIp ?? '' }}'></div>
<div class="nx-fhead"><nobr>End IP:</nobr></div><div class="nx-fcell"><input type='text' class="w-full" size=30 name='range_end_ip' aria-label='Range end IP' value='{{ $rangeEndIp ?? '' }}'><input type='hidden' name='check_range' value='true'></div>
<div class="nx-ffull nx-toolbox text-center"><input class='nx-btn nx-btn--primary' type='Submit'></div>
</div>
</form>
</div>

@if ($hasRangeFilter ?? false)
    <p><strong>{{ $message ?? '' }}</strong></p>
@else
    <p><strong>{{ ($success ?? false) ? '(Updated!)' : '' }}Existing Locations:</strong></p>
@endif
<x-data-table caption="Manage Locations" captionHidden><x-slot:head><thead><tr>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>ID</b></th>
<th class="bg-nxm-surface-alt font-semibold text-left" scope="col"><b>Name</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>Pic</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b><nobr>Main Location</nobr></b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b><nobr>Sub Location</nobr></b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>Start IP</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>End IP</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>T.U</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>P.U</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>T.D</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>P.D</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>Edit</b></th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"><b>Delete</b></th>
</tr></thead></x-slot:head>
@foreach ($rows ?? [] as $row)
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
<td class="align-top px-2.5 py-1.5 text-center"><a href='{{ $actionUrl ?? '' }}?editid={{ (int) $row['id'] }}'>Edit</a></td>
<td class="align-top px-2.5 py-1.5 text-center"><a href='{{ $actionUrl ?? '' }}?delid={{ (int) $row['id'] }}'>Remove</a></td>
</tr>
@endforeach
</x-data-table>
{{ $pagerbottom ?? '' }}
@endif
</x-frame>
</x-page>
@endsection
