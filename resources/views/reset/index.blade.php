@extends('layouts.app')

@section('title', "Reset User's Lost Password")

@section('content')
<x-page title="Reset User's Lost Password" width="form">
@if (! empty($success))
<p>{{ $message ?? '' }}</p>
@endif
<form method="post">@csrf
<div class="nx-fgrid">
<label class="nx-fhead" for="reset-username">User Name:</label><div class="nx-fcell"><input size="40" id="reset-username" name="username" autocomplete="username"></div>
<label class="nx-fhead" for="reset-newpassword">New Password:</label><div class="nx-fcell"><input type="password" size="40" id="reset-newpassword" name="newpassword" autocomplete="new-password"><br /><span class="small">Minimum is 6 characters</span></div>
<label class="nx-fhead" for="reset-newpasswordagain">Confirm New Password:</label><div class="nx-fcell"><input type="password" size="40" id="reset-newpasswordagain" name="newpasswordagain" autocomplete="new-password"></div>
<div class="nx-ffull text-center"><input type="submit" class="nx-btn nx-btn--primary" value="Reset"></div>
</div>
</form>
</x-page>
@endsection
