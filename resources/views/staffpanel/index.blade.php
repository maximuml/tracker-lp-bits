@extends('layouts.legacy')

@section('title', ('Administration'))

@section('content')
<h1 align=center>{{ ('Administration')}}</h1>

@if (! empty($sysopPanels))
<h1 align=center>..:: {{ 'For SysOp Only' }} ::..</h1>
<br /><br />
<table data-nx="data" width=80% border=1 cellspacing=0 cellpadding=5 align=center>
<tr><td class=colhead align=left>{{ 'Option Name' }}</td><td class=colhead align=left>{{ ('Info')}}</td></tr>
@foreach ($sysopPanels as $row)
<tr>
    <td class=rowfollow align=left><strong><a href="{{ $row['url'] }}">{{ $row['name'] }}</a></strong></td>
    <td class=rowfollow align=left>{{ $row['info'] }}</td>
</tr>
@endforeach
</table>
<br /><br />
@endif

@if (! empty($adminPanels))
<h1 align=center>..:: {{ 'For Administrator Only' }} ::..</h1>
<br /><br />
<table data-nx="data" width=80% border=1 cellspacing=0 cellpadding=5 align=center>
<tr><td class=colhead align=left>{{ 'Option Name' }}</td><td class=colhead align=left>{{ ('Info')}}</td></tr>
@foreach ($adminPanels as $row)
<tr>
    <td class=rowfollow align=left><strong><a href="{{ $row['url'] }}">{{ $row['name'] }}</a></strong></td>
    <td class=rowfollow align=left>{{ $row['info'] }}</td>
</tr>
@endforeach
</table>
<br /><br />
@endif

@if (! empty($modPanels))
<h1 align=center>..:: {{ 'For Moderator Only' }} ::..</h1>
<br /><br />
<table data-nx="data" width=80% border=1 cellspacing=0 cellpadding=5 align=center>
<tr><td class=colhead align=left>{{ 'Option Name' }}</td><td class=colhead align=left>{{ ('Info')}}</td></tr>
@foreach ($modPanels as $row)
<tr>
    <td class=rowfollow align=left><strong><a href="{{ $row['url'] }}">{{ $row['name'] }}</a></strong></td>
    <td class=rowfollow align=left>{{ $row['info'] }}</td>
</tr>
@endforeach
</table>
<br /><br />
@endif
@endsection
