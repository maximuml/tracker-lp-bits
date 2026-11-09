@extends('layouts.app')

@section('title', "Reset User's Lost Password")

@section('content')
<x-page title="Reset User's Lost Password" width="form">
@if (! empty($success))
<p>{{ $message ?? '' }}</p>
@endif
<form method="post">@csrf
<div class="nx-fgrid">
<div class="nx-fhead">User Name:</div><div class="nx-fcell"><input size="40" name="username" autocomplete="username"></div>
<div class="nx-fhead">New Password:</div><div class="nx-fcell"><input type="password" size="40" name="newpassword" autocomplete="new-password"><br /><span class="small">Minimum is 6 characters</span></div>
<div class="nx-fhead">Confirm New Password:</div><div class="nx-fcell"><input type="password" size="40" name="newpasswordagain" autocomplete="new-password"></div>
<div class="nx-ffull text-center"><input type="submit" class="nx-btn nx-btn--primary" value="Reset"></div>
</div>
</form>
</x-page>
@endsection
