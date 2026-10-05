@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', ('Administration'))

@section('content')
<h1 class="text-center">{{ ('Administration')}}</h1>

@if (! empty($sysopPanels))
<h1 class="text-center">..:: {{ 'For SysOp Only' }} ::..</h1>
<br /><br />
<x-data-table caption="For SysOp Only" captionHidden class="w-[80%] mx-auto"><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ 'Option Name' }}</th><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ ('Info')}}</th></tr></thead></x-slot:head>
@foreach ($sysopPanels as $row)
<tr>
    <td class="align-top px-2.5 py-1.5"><strong><a href="{{ $row['url'] }}">{{ $row['name'] }}</a></strong></td>
    <td class="align-top px-2.5 py-1.5">{{ $row['info'] }}</td>
</tr>
@endforeach
</x-data-table>
<br /><br />
@endif

@if (! empty($adminPanels))
<h1 class="text-center">..:: {{ 'For Administrator Only' }} ::..</h1>
<br /><br />
<x-data-table caption="For Administrator Only" captionHidden class="w-[80%] mx-auto"><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ 'Option Name' }}</th><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ ('Info')}}</th></tr></thead></x-slot:head>
@foreach ($adminPanels as $row)
<tr>
    <td class="align-top px-2.5 py-1.5"><strong><a href="{{ $row['url'] }}">{{ $row['name'] }}</a></strong></td>
    <td class="align-top px-2.5 py-1.5">{{ $row['info'] }}</td>
</tr>
@endforeach
</x-data-table>
<br /><br />
@endif

@if (! empty($modPanels))
<h1 class="text-center">..:: {{ 'For Moderator Only' }} ::..</h1>
<br /><br />
<x-data-table caption="For Moderator Only" captionHidden class="w-[80%] mx-auto"><x-slot:head><thead><tr><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ 'Option Name' }}</th><th class="bg-nxm-surface-alt font-semibold text-left" scope="col">{{ ('Info')}}</th></tr></thead></x-slot:head>
@foreach ($modPanels as $row)
<tr>
    <td class="align-top px-2.5 py-1.5"><strong><a href="{{ $row['url'] }}">{{ $row['name'] }}</a></strong></td>
    <td class="align-top px-2.5 py-1.5">{{ $row['info'] }}</td>
</tr>
@endforeach
</x-data-table>
<br /><br />
@endif
@endsection
