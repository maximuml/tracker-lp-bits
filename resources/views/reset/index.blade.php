@extends('layouts.legacy')

@section('title', "Reset User's Lost Password")

@section('content')
@if (! empty($success))
<p>{{ $message ?? '' }}</p>
@endif
<table border=1 cellspacing=0 cellpadding=5>
<form method=post>
<tr><td class=colhead align="center" colspan=2>Reset User's Lost Password</td></tr>
<tr><td class=rowhead align="right">User Name:</td><td class=rowfollow><input size=40 name=username></td></tr>
<tr><td class=rowhead align="right">New Password:</td><td class=rowfollow><input type="password" size=40 name=newpassword><br /><font class=small>Minimum is 6 characters</font></td></tr>
<tr><td class=rowhead align="right">Confirm New Password:</td><td class=rowfollow><input type="password" size=40 name=newpasswordagain></td></tr>
<tr><td class=toolbox colspan=2 align="center"><input type=submit class=btn value='Reset'></td></tr>
</form>
</table>
@endsection
