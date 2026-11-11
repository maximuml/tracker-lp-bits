@extends('layouts.app')

@section('title', "Add user")

@section('content')
<x-page :title="'Add user'" width="form">
<form method=post action="/web/admin/users/add">@csrf
<div class="nx-fgrid">
<label class="nx-fhead" for="adduser-username">{{ __('adduser.username') }}</label><div class="nx-fcell"><input type=text id="adduser-username" name=username size=40 class="w-full"></div>
<label class="nx-fhead" for="adduser-password">{{ __('adduser.password') }}</label><div class="nx-fcell"><input type=password id="adduser-password" name=password size=40 class="w-full"></div>
<label class="nx-fhead" for="adduser-password2">{{ __('adduser.retype_password') }}</label><div class="nx-fcell"><input type=password id="adduser-password2" name=password2 size=40 class="w-full"></div>
<label class="nx-fhead" for="adduser-email">E-mail</label><div class="nx-fcell"><input type=text id="adduser-email" name=email size=40 class="w-full"></div>
<div class="nx-ffull text-center"><input type="submit" class="nx-btn nx-btn--primary" value="{{ __('comment.submit_okay') }}"></div>
</div>
</form>
</x-page>
@endsection
