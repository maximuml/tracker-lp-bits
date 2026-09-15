@extends('layouts.legacy')

@section('title', "Reset User's Lost Password")

@section('content')
@if (! empty($success))
<p>{{ $message ?? '' }}</p>
@endif
<form method=post>
<div class="nx-fgrid">
<div class="nx-colhead nx-ffull nx-center">Reset User's Lost Password</div>
<div class="nx-fhead">User Name:</div><div class="nx-fcell"><input size=40 name=username></div>
<div class="nx-fhead">New Password:</div><div class="nx-fcell"><input type="password" size=40 name=newpassword><br /><font class=small>Minimum is 6 characters</font></div>
<div class="nx-fhead">Confirm New Password:</div><div class="nx-fcell"><input type="password" size=40 name=newpasswordagain></div>
<div class="nx-ffull nx-center"><input type=submit class=btn value='Reset'></div>
</div>
</form>
@endsection
