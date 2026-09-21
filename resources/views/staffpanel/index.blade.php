@extends('layouts.legacy')

@section('title', ('Administration'))

@section('content')
<h1 class="nx-center">{{ ('Administration')}}</h1>

@if (! empty($sysopPanels))
<h1 class="nx-center">..:: {{ 'For SysOp Only' }} ::..</h1>
<br /><br />
<table data-nx="data" class="nx-w-80 nx-mx-auto">
<tr><th class="colhead nx-align-left" scope="col">{{ 'Option Name' }}</th><th class="colhead nx-align-left" scope="col">{{ ('Info')}}</th></tr>
@foreach ($sysopPanels as $row)
<tr>
    <td class="rowfollow"><strong><a href="{{ $row['url'] }}">{{ $row['name'] }}</a></strong></td>
    <td class="rowfollow">{{ $row['info'] }}</td>
</tr>
@endforeach
</table>
<br /><br />
@endif

@if (! empty($adminPanels))
<h1 class="nx-center">..:: {{ 'For Administrator Only' }} ::..</h1>
<br /><br />
<table data-nx="data" class="nx-w-80 nx-mx-auto">
<tr><th class="colhead nx-align-left" scope="col">{{ 'Option Name' }}</th><th class="colhead nx-align-left" scope="col">{{ ('Info')}}</th></tr>
@foreach ($adminPanels as $row)
<tr>
    <td class="rowfollow"><strong><a href="{{ $row['url'] }}">{{ $row['name'] }}</a></strong></td>
    <td class="rowfollow">{{ $row['info'] }}</td>
</tr>
@endforeach
</table>
<br /><br />
@endif

@if (! empty($modPanels))
<h1 class="nx-center">..:: {{ 'For Moderator Only' }} ::..</h1>
<br /><br />
<table data-nx="data" class="nx-w-80 nx-mx-auto">
<tr><th class="colhead nx-align-left" scope="col">{{ 'Option Name' }}</th><th class="colhead nx-align-left" scope="col">{{ ('Info')}}</th></tr>
@foreach ($modPanels as $row)
<tr>
    <td class="rowfollow"><strong><a href="{{ $row['url'] }}">{{ $row['name'] }}</a></strong></td>
    <td class="rowfollow">{{ $row['info'] }}</td>
</tr>
@endforeach
</table>
<br /><br />
@endif
@endsection
