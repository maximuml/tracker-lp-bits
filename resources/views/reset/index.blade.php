@extends('layouts.app')

@section('title', __('reset.title'))

@section('content')
<x-page :title="__('reset.title')" width="form">
@if (! empty($success))
<p>{{ $message ?? '' }}</p>
@endif
<form method="post">@csrf
<div class="nx-fgrid">
<label class="nx-fhead" for="reset-username">{{ __('reset.row_username') }}</label><div class="nx-fcell"><input size="40" id="reset-username" name="username" autocomplete="username"></div>
<label class="nx-fhead" for="reset-newpassword">{{ __('reset.row_new_password') }}</label><div class="nx-fcell"><input type="password" size="40" id="reset-newpassword" name="newpassword" autocomplete="new-password"><br /><span class="small">{{ __('reset.text_min_length') }}</span></div>
<label class="nx-fhead" for="reset-newpasswordagain">{{ __('reset.row_confirm_password') }}</label><div class="nx-fcell"><input type="password" size="40" id="reset-newpasswordagain" name="newpasswordagain" autocomplete="new-password"></div>
<div class="nx-ffull text-center"><input type="submit" class="nx-btn nx-btn--primary" value="{{ __('reset.submit_reset') }}" data-submit-once></div>
</div>
</form>
</x-page>
@endsection
