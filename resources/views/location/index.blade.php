@extends('layouts.legacy')

@section('title', 'Manage Locations')

@section('content')
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open('Manage Locations', true, 10, '100%', 'center')))
@if ($error ?? '')
    <p><strong>{{ $error }}</strong></p>
@endif

@if (($mode ?? '') === 'edit' && ! empty($editRow))
<form name='form1' method='get' action='{{ $actionUrl ?? '' }}'>
<input type='hidden' name='id' value='{{ (int) $editRow['id'] }}'>
<input type='hidden' name='edited' value='1'>
<table class='main' cellspacing=0 cellpadding=5 width=50%>
<tr><td class=colhead align=center colspan=2>Editing Locations</td></tr>
<tr><td class=rowhead>Name:</td><td class=rowfollow align=left><input type='text' size=10 name='name' value='{{ $editRow['name'] }}'></td></tr>
<tr><td class=rowhead><nobr>Main Location:</nobr></td><td class=rowfollow align=left><input type='text' size=50 name='location_main' value='{{ $editRow['location_main'] }}'></td></tr>
<tr><td class=rowhead><nobr>Sub Location:</nobr></td><td class=rowfollow align=left><input type='text' size=50 name='location_sub' value='{{ $editRow['location_sub'] }}'></td></tr>
<tr><td class=rowhead><nobr>Start IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='start_ip' value='{{ $editRow['start_ip'] }}'></td></tr>
<tr><td class=rowhead><nobr>End IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='end_ip' value='{{ $editRow['end_ip'] }}'></td></tr>
<tr><td class=rowhead><nobr>Theory Up:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='theory_upspeed' value='{{ $editRow['theory_upspeed'] }}'></td></tr>
<tr><td class=rowhead><nobr>Theory Down:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='theory_downspeed' value='{{ $editRow['theory_downspeed'] }}'></td></tr>
<tr><td class=rowhead><nobr>Practical Up:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='practical_upspeed' value='{{ $editRow['practical_upspeed'] }}'></td></tr>
<tr><td class=rowhead><nobr>Practical Down:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='practical_downspeed' value='{{ $editRow['practical_downspeed'] }}'></td></tr>
<tr><td class=rowhead>Picture:</td><td class=rowfollow align=left><input type='text' size=50 name='flagpic' value='{{ $editRow['flagpic'] }}'></td></tr>
<tr><td class=toolbox align=center colspan=2><input class=btn type='Submit'></td></tr>
</table>
</form>
@else
<form name='form1' method='get' action='{{ $actionUrl ?? '' }}'>
<table class='main' cellspacing=0 cellpadding=5 width=48% align=left>
<tr><td class=colhead align=center colspan=2>Add New Locations</td></tr>
<tr><td class=rowhead>Name:</td><td class=rowfollow align=left><input type='text' size=10 name='name'></td></tr>
<tr><td class=rowhead><nobr>Main Location:</nobr></td><td class=rowfollow align=left><input type='text' size=50 name='location_main'></td></tr>
<tr><td class=rowhead><nobr>Sub Location:</nobr></td><td class=rowfollow align=left><input type='text' size=50 name='location_sub'></td></tr>
<tr><td class=rowhead><nobr>Start IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='start_ip'></td></tr>
<tr><td class=rowhead><nobr>End IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='end_ip'></td></tr>
<tr><td class=rowhead><nobr>Theory Up:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='theory_upspeed'></td></tr>
<tr><td class=rowhead><nobr>Theory Down:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='theory_downspeed'></td></tr>
<tr><td class=rowhead><nobr>Practical Up:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='practical_upspeed'></td></tr>
<tr><td class=rowhead><nobr>Practical Down:</nobr></td><td class=rowfollow align=left><input type='text' size=10 name='practical_downspeed'></td></tr>
<tr><td class=rowhead>Picture:</td><td class=rowfollow align=left><input type='text' size=50 name='flagpic'><input type='hidden' name='add' value='true'></td></tr>
<tr><td class=toolbox align=center colspan=2><input class=btn type='Submit'></td></tr>
</table>
</form>

<form name='form2' method='get' action='{{ $actionUrl ?? '' }}'>
<table class='main' cellspacing=0 cellpadding=5 width=48% align=right>
<tr><td class=colhead align=center colspan=2>Check IP Range</td></tr>
<tr><td class=rowhead><nobr>Start IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='range_start_ip' value='{{ $rangeStartIp ?? '' }}'></td></tr>
<tr><td class=rowhead><nobr>End IP:</nobr></td><td class=rowfollow align=left><input type='text' size=30 name='range_end_ip' value='{{ $rangeEndIp ?? '' }}'><input type='hidden' name='check_range' value='true'></td></tr>
<tr><td class=toolbox align=center colspan=2><input class=btn type='Submit'></td></tr>
</table>
</form>

<br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br />

@if ($hasRangeFilter ?? false)
    <p><strong>{{ $message ?? '' }}</strong></p>
@else
    <p><strong>{{ ($success ?? false) ? '(Updated!)' : '' }}Existing Locations:</strong></p>
@endif
<table class='main' cellspacing=0 cellpadding=5>
<tr>
<td class=colhead align=center><b>ID</b></td>
<td class=colhead align=left><b>Name</b></td>
<td class=colhead align=center><b>Pic</b></td>
<td class=colhead align=center><b><nobr>Main Location</nobr></b></td>
<td class=colhead align=center><b><nobr>Sub Location</nobr></b></td>
<td class=colhead align=center><b>Start IP</b></td>
<td class=colhead align=center><b>End IP</b></td>
<td class=colhead align=center><b>T.U</b></td>
<td class=colhead align=center><b>P.U</b></td>
<td class=colhead align=center><b>T.D</b></td>
<td class=colhead align=center><b>P.D</b></td>
<td class=colhead align=center><b>Edit</b></td>
<td class=colhead align=center><b>Delete</b></td>
</tr>
@foreach ($rows ?? [] as $row)
<tr>
<td class=rowfollow align=center><strong>{{ (int) $row['id'] }}</strong></td>
<td class=rowfollow align=left><strong>{{ $row['name'] }}</strong></td>
<td class=rowfollow align=center>@if ($row['flagpic_url'] ?? '')<img src="{{ $row['flagpic_url'] }}" border="0" />@else-@endif</td>
<td class=rowfollow align=left>{{ $row['location_main'] }}</td>
<td class=rowfollow align=left>{{ $row['location_sub'] }}</td>
<td class=rowfollow align=left>{{ $row['start_ip'] }}</td>
<td class=rowfollow align=left>{{ $row['end_ip'] }}</td>
<td class=rowfollow align=left>{{ $row['theory_upspeed'] }}</td>
<td class=rowfollow align=left>{{ $row['practical_upspeed'] }}</td>
<td class=rowfollow align=left>{{ $row['theory_downspeed'] }}</td>
<td class=rowfollow align=left>{{ $row['practical_downspeed'] }}</td>
<td class=rowfollow align=center><a href='{{ $actionUrl ?? '' }}?editid={{ (int) $row['id'] }}'>Edit</a></td>
<td class=rowfollow align=center><a href='{{ $actionUrl ?? '' }}?delid={{ (int) $row['id'] }}'>Remove</a></td>
</tr>
@endforeach
</table>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
@endif
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))
@endsection
