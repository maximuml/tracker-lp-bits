@extends('layouts.app')

@section('title', "Add user")

@section('content')
<h1>Add user</h1>
<form method=post action="/web/admin/users/add">@csrf
<div class="nx-fgrid">
<label class="nx-fhead" for="adduser-username">User name</label><div class="nx-fcell"><input type=text id="adduser-username" name=username size=40></div>
<label class="nx-fhead" for="adduser-password">Password</label><div class="nx-fcell"><input type=password id="adduser-password" name=password size=40></div>
<label class="nx-fhead" for="adduser-password2">Re-type password</label><div class="nx-fcell"><input type=password id="adduser-password2" name=password2 size=40></div>
<label class="nx-fhead" for="adduser-email">E-mail</label><div class="nx-fcell"><input type=text id="adduser-email" name=email size=40></div>
<div class="nx-ffull text-center"><input type=submit value="Okay" class=btn></div>
</div>
</form>
@endsection
